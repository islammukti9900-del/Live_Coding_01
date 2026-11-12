<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_dan_crud_buku(): void
    {
        // Register harus 201
        $this->postJson('/api/register', [
            'name' => 'Islam Mukti',
            'email' => 'islam@example.com',
            'password' => 'password123',
        ])->assertStatus(201)->assertJsonPath('success', true);

        // Login password salah harus 401 dengan pesan yang benar
        $this->postJson('/api/login', [
            'email' => 'islam@example.com',
            'password' => 'salahpassword',
        ])->assertStatus(401)->assertJsonPath('message', 'Email atau password salah');

        // Login benar harus dapat token
        $login = $this->postJson('/api/login', [
            'email' => 'islam@example.com',
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonPath('success', true);

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);
        $headers = ['Authorization' => 'Bearer '.$token];

        // /user dengan token harus 200
        $this->withHeaders($headers)->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('email', 'islam@example.com');

        // Buat buku harus 201
        $book = $this->withHeaders($headers)->postJson('/api/books', [
            'judul' => 'Laskar Pelangi',
            'penulis' => 'Andrea Hirata',
            'tahun' => 2005,
        ])->assertStatus(201);
        $bookId = $book->json('data.id');

        // Ambil buku yang ada harus 200
        $this->withHeaders($headers)->getJson("/api/books/{$bookId}")->assertStatus(200);

        // REGRESI UTAMA: buku tidak ditemukan harus 404, bukan 500
        $this->withHeaders($headers)->getJson('/api/books/99999')
            ->assertStatus(404)->assertJsonPath('message', 'Buku tidak ditemukan');
        $this->withHeaders($headers)->putJson('/api/books/99999', [
            'judul' => 'X', 'penulis' => 'Y', 'tahun' => 2020,
        ])->assertStatus(404);
        $this->withHeaders($headers)->deleteJson('/api/books/99999')
            ->assertStatus(404);

        // Update & hapus buku yang ada harus berhasil
        $this->withHeaders($headers)->putJson("/api/books/{$bookId}", [
            'judul' => 'Sang Pemimpi',
        ])->assertStatus(200)->assertJsonPath('data.judul', 'Sang Pemimpi');
        $this->withHeaders($headers)->deleteJson("/api/books/{$bookId}")->assertStatus(200);

        // Logout harus mencabut token yang sedang dipakai
        $this->withHeaders($headers)->postJson('/api/logout')->assertStatus(200);
        $this->assertEquals(0, \Illuminate\Support\Facades\DB::table('personal_access_tokens')->count());

        // Paksa guard dibuat ulang supaya request berikutnya benar-benar
        // mengecek token ke database (meniru proses PHP baru di production)
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/user')->assertStatus(401);
    }

    public function test_login_kena_throttle_setelah_5x_gagal(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Throttle Test',
            'email' => 'throttle@example.com',
            'password' => 'password123',
        ])->assertStatus(201);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'throttle@example.com',
                'password' => 'salahpassword',
            ])->assertStatus(401);
        }

        // Percobaan ke-6 dalam semenit harus ditolak 429
        $this->postJson('/api/login', [
            'email' => 'throttle@example.com',
            'password' => 'password123',
        ])->assertStatus(429);
    }
}

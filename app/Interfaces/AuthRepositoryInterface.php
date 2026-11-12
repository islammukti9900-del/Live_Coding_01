<?php

namespace App\Interfaces;

interface AuthRepositoryInterface
{
    public function create(array $data);
    public function findByEmail($email);
}

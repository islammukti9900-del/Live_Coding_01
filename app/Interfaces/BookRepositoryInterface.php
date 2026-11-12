<?php

namespace App\Interfaces;

interface BookRepositoryInterface
{
    public function getAll($search = null);
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
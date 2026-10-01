<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Ana Administradora', 'email' => 'admin@pollocharly.com', 'role' => Role::ADMINISTRADOR],
            ['name' => 'Carlos Mesero', 'email' => 'mesero@pollocharly.com', 'role' => Role::MESERO_CAJERO],
            ['name' => 'Beto Cocinero', 'email' => 'cocinero@pollocharly.com', 'role' => Role::COCINERO],
        ];

        foreach ($users as $data) {
            User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role_id' => Role::query()->where('name', $data['role'])->value('id'),
                ]
            );
        }
    }
}

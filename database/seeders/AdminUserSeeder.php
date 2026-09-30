<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('initial_admin.email');
        $password = config('initial_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('INITIAL_ADMIN_EMAIL o INITIAL_ADMIN_PASSWORD no están definidos: se omite el administrador inicial.');

            return;
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('initial_admin.name'),
                'password' => Hash::make($password),
                'role_id' => Role::query()->where('name', Role::ADMINISTRADOR)->value('id'),
            ]
        );
    }
}

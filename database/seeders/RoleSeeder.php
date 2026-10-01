<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        collect([
            Role::ADMINISTRADOR,
            Role::MESERO_CAJERO,
            Role::COCINERO,
        ])->each(fn (string $name) => Role::query()->firstOrCreate(['name' => $name]));
    }
}

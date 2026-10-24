<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Actions\Users\GenerateTemporaryPasswordAction;
use App\Models\Role;
use App\Models\User;
use App\Support\Demo\DemoCatalog;

final class CreateDemoUsersAction
{
    public function __construct(private readonly GenerateTemporaryPasswordAction $generatePassword) {}

    /**
     * Crea los usuarios de demostración con contraseñas aleatorias. Las contraseñas solo existen en el valor devuelto:
     * no se guardan en ningún otro lugar ni se envían por correo.
     *
     * @return array<int, array{user: User, password: string}>
     */
    public function handle(): array
    {
        $roles = Role::query()->pluck('id', 'name');
        $created = [];

        foreach (DemoCatalog::users() as $definition) {
            $password = $this->generatePassword->handle();

            $user = User::query()->create([
                'name' => $definition['name'],
                'email' => $definition['email'],
                'password' => $password,
                'role_id' => $roles[$definition['role']],
                'is_active' => true,
                'must_change_password' => false,
            ]);

            $created[] = ['user' => $user, 'password' => $password];
        }

        return $created;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class CreateUserAction
{
    public function __construct(
        private readonly GenerateTemporaryPasswordAction $generateTemporaryPassword,
        private readonly SendTemporaryCredentialsAction $sendTemporaryCredentials,
    ) {}

    /**
     * Crea el usuario con una contraseña temporal aleatoria que solo se envía por correo.
     *
     * @param  array{name: string, email: string, role_id: int, is_active?: bool}  $data
     * @return array{user: User, credentials_sent: bool}
     */
    public function handle(array $data): array
    {
        $temporaryPassword = $this->generateTemporaryPassword->handle();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return [
            'user' => $user,
            'credentials_sent' => $this->sendTemporaryCredentials->handle($user, $temporaryPassword, isReset: false),
        ];
    }
}

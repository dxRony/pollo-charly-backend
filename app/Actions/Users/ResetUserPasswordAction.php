<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class ResetUserPasswordAction
{
    public function __construct(
        private readonly GenerateTemporaryPasswordAction $generateTemporaryPassword,
        private readonly SendTemporaryCredentialsAction $sendTemporaryCredentials,
    ) {}

    /**
     * Reemplaza la contraseña por una temporal nueva, cierra las sesiones activas y la envía por correo.
     *
     * @return bool Indica si el correo con la contraseña temporal pudo enviarse.
     */
    public function handle(User $user): bool
    {
        $temporaryPassword = $this->generateTemporaryPassword->handle();

        $user->password = $temporaryPassword;
        $user->must_change_password = true;
        $user->save();

        $user->tokens()->delete();

        return $this->sendTemporaryCredentials->handle($user, $temporaryPassword, isReset: true);
    }
}

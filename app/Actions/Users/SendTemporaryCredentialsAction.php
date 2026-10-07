<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\TemporaryCredentialsNotification;
use Throwable;

final class SendTemporaryCredentialsAction
{
    /**
     * Envía la contraseña temporal al correo del usuario.
     *
     * Un fallo del correo no debe deshacer la operación (el usuario ya quedó guardado), por eso
     * se reporta y se devuelve false para que la API pueda avisar y permitir reintentar.
     */
    public function handle(User $user, string $temporaryPassword, bool $isReset): bool
    {
        try {
            $user->notify(new TemporaryCredentialsNotification($temporaryPassword, $isReset));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}

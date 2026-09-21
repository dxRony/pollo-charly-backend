<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * Verify credentials and handle 2FA or direct token issuance.
     *
     * @return array{two_factor_required: false, user: User, token: string}|array{two_factor_required: true, email: string}
     */
    public function handle(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta ha sido desactivada. Por favor contacta a la administración.'],
            ]);
        }

        if ($user->two_factor_enabled) {
            $code = $user->generateTwoFactorCode();
            $user->notify(new TwoFactorCodeNotification($code));

            return [
                'two_factor_required' => true,
                'email' => $user->email,
            ];
        }

        $token = $user->createToken('spa')->plainTextToken;

        return [
            'two_factor_required' => false,
            'user' => $user->loadMissing('role'),
            'token' => $token,
        ];
    }
}

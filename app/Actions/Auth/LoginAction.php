<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * Verify the credentials and issue a fresh API token for the user.
     *
     * @return array{user: User, token: string}
     */
    public function handle(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return ['user' => $user->loadMissing('role'), 'token' => $token];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ChangePasswordAction
{
    /**
     * Update the user's password and revoke every other active session.
     *
     * The session performing the change (current token) is kept so the user is not logged out.
     */
    public function handle(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();

        $currentToken = $user->currentAccessToken();

        $user->tokens()
            ->when(
                $currentToken instanceof PersonalAccessToken,
                fn ($query) => $query->where('id', '!=', $currentToken->getKey()),
            )
            ->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Rutas permitidas mientras el usuario tenga una contraseña temporal pendiente de cambiar.
     */
    private const ALLOWED_PATHS = ['api/me', 'api/me/password', 'api/logout'];

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->is(self::ALLOWED_PATHS)) {
            return response()->json([
                'message' => 'Debes cambiar tu contraseña temporal antes de continuar.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Demo;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Cliente interno del API: ejecuta las peticiones dentro del mismo proceso, por el mismo camino que una petición real
 * (middleware, validación, reglas de negocio y eventos), autenticadas con un token de Sanctum del usuario indicado.
 *
 * Así los datos de demostración respetan exactamente las reglas del sistema (reservas y consumo de stock, estados de
 * las comandas, alertas, compras...), en lugar de reescribirlas con inserciones directas.
 */
final class DemoApi
{
    public const TOKEN_NAME = 'demo-seed';

    /** @var array<int, string> */
    private array $tokens = [];

    public function __construct(private readonly Kernel $kernel) {}

    /**
     * Ejecuta la petición y devuelve el cuerpo JSON. Lanza DemoApiException si la respuesta no es exitosa.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(User $actor, string $method, string $uri, array $payload = []): array
    {
        [$status, $body] = $this->send($actor, $method, $uri, $payload);

        if ($status >= 400) {
            throw new DemoApiException($method, $uri, $status, $body);
        }

        return $body;
    }

    /**
     * Igual que call(), pero devuelve también el código HTTP en lugar de lanzar excepción ante un rechazo del sistema.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function send(User $actor, string $method, string $uri, array $payload = []): array
    {
        $method = strtoupper($method);
        $isRead = $method === 'GET';

        $request = Request::create(
            '/api'.$uri,
            $method,
            $isRead ? $payload : [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($actor),
            ],
            $isRead ? null : ($payload === [] ? '{}' : json_encode($payload, JSON_THROW_ON_ERROR)),
        );

        // Los guards recuerdan al último usuario autenticado: se reinician para que cada petición use su propio token.
        app('auth')->forgetGuards();

        // El reloj simulado avanza día a día: los tokens temporales de la carga no deben vencer por la vigencia de sesión.
        $expiration = config('sanctum.expiration');
        config(['sanctum.expiration' => null]);

        try {
            $response = $this->kernel->handle($request);
            $status = $response->getStatusCode();
            $decoded = json_decode((string) $response->getContent(), true);
            $this->kernel->terminate($request, $response);
        } finally {
            config(['sanctum.expiration' => $expiration]);
        }

        return [$status, is_array($decoded) ? $decoded : []];
    }

    /** Elimina los tokens temporales creados durante la carga. */
    public function revokeTokens(): void
    {
        PersonalAccessToken::query()->where('name', self::TOKEN_NAME)->delete();
        $this->tokens = [];
    }

    private function tokenFor(User $user): string
    {
        return $this->tokens[$user->id] ??= $user->createToken(self::TOKEN_NAME)->plainTextToken;
    }
}

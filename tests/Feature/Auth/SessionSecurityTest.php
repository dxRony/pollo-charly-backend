<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function usuarioDeSesion(string $email = 'sesion@pollocharly.com'): User
{
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => $email,
        'password' => 'passwordCorrecta1',
    ]);
}

test('el inicio de sesión se bloquea tras 5 intentos por correo y responde en español', function () {
    usuarioDeSesion();

    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/login', ['email' => 'sesion@pollocharly.com', 'password' => 'incorrecta'])
            ->assertStatus(422);
    }

    $this->postJson('/api/login', ['email' => 'sesion@pollocharly.com', 'password' => 'passwordCorrecta1'])
        ->assertStatus(429)
        ->assertJson(['message' => 'Demasiados intentos. Espera un minuto antes de intentarlo de nuevo.'])
        ->assertHeader('Retry-After');
});

test('el límite del inicio de sesión no distingue mayúsculas ni afecta a otros correos', function () {
    usuarioDeSesion();
    usuarioDeSesion('otra@pollocharly.com');

    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/login', ['email' => 'SESION@PolloCharly.com ', 'password' => 'incorrecta']);
    }

    $this->postJson('/api/login', ['email' => 'sesion@pollocharly.com', 'password' => 'passwordCorrecta1'])
        ->assertStatus(429);

    $this->postJson('/api/login', ['email' => 'otra@pollocharly.com', 'password' => 'passwordCorrecta1'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('un inicio de sesión normal no se ve afectado por el límite', function () {
    usuarioDeSesion();

    $this->postJson('/api/login', ['email' => 'sesion@pollocharly.com', 'password' => 'passwordCorrecta1'])
        ->assertOk();
});

test('la solicitud de recuperación de contraseña se limita a 3 por minuto y correo', function () {
    Notification::fake();
    usuarioDeSesion();

    foreach (range(1, 3) as $intento) {
        $this->postJson('/api/forgot-password', ['email' => 'sesion@pollocharly.com'])->assertOk();
    }

    $this->postJson('/api/forgot-password', ['email' => 'sesion@pollocharly.com'])->assertStatus(429);
    $this->postJson('/api/forgot-password', ['email' => 'distinto@pollocharly.com'])->assertOk();
});

test('el restablecimiento de contraseña se limita a 5 intentos por minuto y correo', function () {
    usuarioDeSesion();

    $payload = [
        'email' => 'sesion@pollocharly.com',
        'token' => 'token-invalido',
        'password' => 'nuevaPassword2',
        'password_confirmation' => 'nuevaPassword2',
    ];

    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/reset-password', $payload)->assertStatus(422);
    }

    $this->postJson('/api/reset-password', $payload)->assertStatus(429);
});

test('un token deja de ser válido pasadas 12 horas desde su emisión', function () {
    $token = usuarioDeSesion()->createToken('spa')->plainTextToken;

    $this->travel(719)->minutes();
    $this->withToken($token)->getJson('/api/me')->assertOk();

    $this->travel(2)->minutes();
    app('auth')->forgetGuards(); // el guard conserva al usuario de la petición anterior dentro del mismo test
    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

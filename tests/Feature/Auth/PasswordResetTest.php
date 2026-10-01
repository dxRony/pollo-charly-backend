<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

test('solicitud con correo registrado envia notificacion con token y retorna mensaje generico', function () {
    $role = Role::firstOrCreate(['name' => Role::ADMINISTRADOR]);
    $user = User::firstOrCreate(
        ['email' => 'mesero@pollocharly.com'],
        [
            'name' => 'Mesero Charly',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]
    );

    $response = $this->postJson('/api/forgot-password', [
        'email' => 'mesero@pollocharly.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Si el correo existe en el sistema, recibirás un enlace de recuperación de contraseña.',
        ]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('solicitud con correo no registrado muestra el mismo mensaje generico sin revelar existencia', function () {
    $response = $this->postJson('/api/forgot-password', [
        'email' => 'inexistente@pollocharly.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Si el correo existe en el sistema, recibirás un enlace de recuperación de contraseña.',
        ]);

    Notification::assertNothingSent();
});

test('solicitud de recuperacion rechaza correo con formato invalido', function () {
    $response = $this->postJson('/api/forgot-password', [
        'email' => 'correo-invalido',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('restablecimiento con token invalido o expirado es rechazado', function () {
    $role = Role::firstOrCreate(['name' => Role::ADMINISTRADOR]);
    User::firstOrCreate(
        ['email' => 'admin@pollocharly.com'],
        [
            'name' => 'Admin',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]
    );

    $response = $this->postJson('/api/reset-password', [
        'token' => 'token-completamente-invalido',
        'email' => 'admin@pollocharly.com',
        'password' => 'nuevaPassword123',
        'password_confirmation' => 'nuevaPassword123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('restablecimiento rechaza contrasena menor a 8 caracteres o sin confirmacion coincidente', function () {
    $response = $this->postJson('/api/reset-password', [
        'token' => 'token-cualquiera',
        'email' => 'admin@pollocharly.com',
        'password' => 'corta',
        'password_confirmation' => 'diferente',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('restablecimiento exitoso actualiza contrasena e invalida tokens de acceso activos previos', function () {
    $role = Role::firstOrCreate(['name' => Role::ADMINISTRADOR]);
    $user = User::firstOrCreate(
        ['email' => 'reset-test@pollocharly.com'],
        [
            'name' => 'Usuario Test',
            'password' => Hash::make('antiguaPassword123'),
            'role_id' => $role->id,
        ]
    );

    // Crear un token Sanctum previo simulando sesión activa
    $tokenObj = $user->createToken('dispositivo-movil');
    expect($user->tokens()->count())->toBe(1);

    // Generar token de recuperación válido
    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => 'reset-test@pollocharly.com',
        'password' => 'nuevaPassword123',
        'password_confirmation' => 'nuevaPassword123',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión con tu nueva contraseña.',
        ]);

    // Verificar que la nueva contraseña funcione
    $user->refresh();
    expect(Hash::check('nuevaPassword123', $user->password))->toBeTrue();

    // Verificar que todos los tokens previos hayan sido eliminados (HU-03 forzar nuevo login)
    expect($user->tokens()->count())->toBe(0);
});

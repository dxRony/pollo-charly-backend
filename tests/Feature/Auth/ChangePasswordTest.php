<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function usuarioConPassword(string $password = 'passwordActual1'): User
{
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);

    return User::factory()->create([
        'role_id' => $role->id,
        'password' => $password,
    ]);
}

function payloadCambioPassword(array $overrides = []): array
{
    return array_merge([
        'current_password' => 'passwordActual1',
        'password' => 'nuevaPassword2',
        'password_confirmation' => 'nuevaPassword2',
    ], $overrides);
}

test('un usuario autenticado cambia su contraseña con la contraseña actual correcta', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/me/password', payloadCambioPassword())
        ->assertOk()
        ->assertJsonStructure(['message']);

    expect(Hash::check('nuevaPassword2', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('passwordActual1', $user->fresh()->password))->toBeFalse();
});

test('el cambio de contraseña conserva la sesion actual y cierra las demas', function () {
    $user = usuarioConPassword();
    $sesionActual = $user->createToken('spa');
    $otraSesion = $user->createToken('otro-dispositivo');

    $this->withToken($sesionActual->plainTextToken)
        ->putJson('/api/me/password', payloadCambioPassword())
        ->assertOk();

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $sesionActual->accessToken->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otraSesion->accessToken->id]);
});

test('rechaza el cambio si la contraseña actual es incorrecta', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/me/password', payloadCambioPassword(['current_password' => 'incorrecta123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password'])
        ->assertJsonPath('errors.current_password.0', 'La contraseña actual es incorrecta.');

    expect(Hash::check('passwordActual1', $user->fresh()->password))->toBeTrue();
});

test('rechaza una nueva contraseña igual a la actual', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/me/password', payloadCambioPassword([
            'password' => 'passwordActual1',
            'password_confirmation' => 'passwordActual1',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('rechaza la nueva contraseña si la confirmacion no coincide', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/me/password', payloadCambioPassword(['password_confirmation' => 'otraDistinta3']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('rechaza una nueva contraseña de menos de 8 caracteres', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/me/password', payloadCambioPassword([
            'password' => 'corta1',
            'password_confirmation' => 'corta1',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('exige los tres campos del formulario', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/me/password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password', 'password']);
});

test('requiere autenticacion para cambiar la contraseña', function () {
    $this->putJson('/api/me/password', payloadCambioPassword())->assertUnauthorized();
});

test('despues de cambiarla se puede iniciar sesion con la nueva contraseña y no con la anterior', function () {
    $user = usuarioConPassword();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)->putJson('/api/me/password', payloadCambioPassword())->assertOk();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'passwordActual1'])
        ->assertUnprocessable();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nuevaPassword2'])
        ->assertOk()
        ->assertJsonPath('two_factor_required', false);
});

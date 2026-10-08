<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->waiterRole = Role::where('name', 'Mesero/Cajero')->first();
    $this->adminRole = Role::where('name', 'Administrador')->first();

    $this->user = User::factory()->create([
        'name' => 'Carlos López',
        'email' => 'carlos.lopez@pollocharly.com',
        'role_id' => $this->waiterRole->id,
    ]);
});

test('un usuario autenticado puede actualizar su nombre y correo en su perfil', function () {
    $token = $this->user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->putJson('/api/me', [
            'name' => 'Carlos Alberto López',
            'email' => 'carlos.alberto@pollocharly.com',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Perfil actualizado exitosamente.')
        ->assertJsonPath('data.name', 'Carlos Alberto López')
        ->assertJsonPath('data.email', 'carlos.alberto@pollocharly.com')
        ->assertJsonPath('data.role.name', 'Mesero/Cajero');

    $this->user->refresh();
    expect($this->user->name)->toBe('Carlos Alberto López');
    expect($this->user->email)->toBe('carlos.alberto@pollocharly.com');
});

test('el usuario puede actualizar su nombre conservando su propio correo', function () {
    $token = $this->user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->putJson('/api/me', [
            'name' => 'Carlos Editado',
            'email' => $this->user->email,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Carlos Editado')
        ->assertJsonPath('data.email', $this->user->email);
});

test('rechaza la actualizacion si el correo ya esta registrado por otro usuario', function () {
    User::factory()->create([
        'email' => 'otro.usuario@pollocharly.com',
        'role_id' => $this->adminRole->id,
    ]);

    $token = $this->user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->putJson('/api/me', [
            'name' => 'Carlos López',
            'email' => 'otro.usuario@pollocharly.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('rechaza datos invalidos o faltantes en el perfil', function () {
    $token = $this->user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->putJson('/api/me', [
            'name' => '',
            'email' => 'correo-invalido',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);
});

test('requiere autenticacion para actualizar el perfil', function () {
    $response = $this->putJson('/api/me', [
        'name' => 'Intento Anónimo',
        'email' => 'anonimo@pollocharly.com',
    ]);

    $response->assertUnauthorized();
});

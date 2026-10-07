<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Notifications\TemporaryCredentialsNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function administrador(): User
{
    $role = Role::firstOrCreate(['name' => Role::ADMINISTRADOR]);

    return User::factory()->create(['role_id' => $role->id]);
}

function datosNuevoUsuario(array $overrides = []): array
{
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);

    return array_merge([
        'name' => 'Carlos Mesero',
        'email' => 'carlos.mesero@pollocharly.test',
        'role_id' => $role->id,
    ], $overrides);
}

test('el administrador crea un usuario sin definir contraseña y se envia una temporal por correo', function () {
    Notification::fake();
    Sanctum::actingAs(administrador());

    $response = $this->postJson('/api/users', datosNuevoUsuario())
        ->assertCreated()
        ->assertJsonPath('credentials_sent', true)
        ->assertJsonPath('user.must_change_password', true);

    $user = User::where('email', 'carlos.mesero@pollocharly.test')->firstOrFail();

    expect($user->must_change_password)->toBeTrue();

    Notification::assertSentTo($user, TemporaryCredentialsNotification::class, function ($notification) use ($user) {
        return strlen($notification->temporaryPassword) === 12
            && ! $notification->isReset
            && Hash::check($notification->temporaryPassword, $user->password);
    });

    $temporaryPassword = Notification::sent($user, TemporaryCredentialsNotification::class)->first()->temporaryPassword;

    expect($response->json('user'))->not->toHaveKey('password')
        ->and($response->getContent())->not->toContain($temporaryPassword);
});

test('ignora una contraseña enviada por el administrador al crear el usuario', function () {
    Notification::fake();
    Sanctum::actingAs(administrador());

    $this->postJson('/api/users', datosNuevoUsuario(['password' => 'ElegidaPorAdmin1']))->assertCreated();

    $user = User::where('email', 'carlos.mesero@pollocharly.test')->firstOrFail();

    expect(Hash::check('ElegidaPorAdmin1', $user->password))->toBeFalse();
});

test('el usuario se crea aunque falle el envio del correo y se informa al administrador', function () {
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('send')->andThrow(new RuntimeException('SES no disponible'));
    $dispatcher->shouldReceive('sendNow')->andThrow(new RuntimeException('SES no disponible'));
    $this->app->instance(Dispatcher::class, $dispatcher);

    Sanctum::actingAs(administrador());

    $this->postJson('/api/users', datosNuevoUsuario())
        ->assertCreated()
        ->assertJsonPath('credentials_sent', false);

    $this->assertDatabaseHas('users', ['email' => 'carlos.mesero@pollocharly.test', 'must_change_password' => true]);
});

test('editar un usuario ya no permite cambiar su contraseña', function () {
    Sanctum::actingAs(administrador());
    $role = Role::firstOrCreate(['name' => Role::COCINERO]);
    $user = User::factory()->create(['role_id' => $role->id, 'password' => 'PasswordOriginal1']);

    $this->putJson("/api/users/{$user->id}", [
        'name' => 'Nombre Editado',
        'email' => $user->email,
        'role_id' => $role->id,
        'password' => 'ElegidaPorAdmin1',
    ])->assertOk();

    expect(Hash::check('PasswordOriginal1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->name)->toBe('Nombre Editado');
});

test('el administrador restablece la contraseña de otro usuario con una temporal nueva', function () {
    Notification::fake();
    Sanctum::actingAs(administrador());
    $role = Role::firstOrCreate(['name' => Role::COCINERO]);
    $user = User::factory()->create(['role_id' => $role->id, 'password' => 'PasswordOriginal1']);
    $user->createToken('spa');

    $this->postJson("/api/users/{$user->id}/reset-password")
        ->assertOk()
        ->assertJsonPath('credentials_sent', true)
        ->assertJsonPath('user.must_change_password', true);

    expect(Hash::check('PasswordOriginal1', $user->fresh()->password))->toBeFalse()
        ->and($user->fresh()->must_change_password)->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    Notification::assertSentTo($user, TemporaryCredentialsNotification::class, function ($notification) use ($user) {
        return $notification->isReset && Hash::check($notification->temporaryPassword, $user->fresh()->password);
    });
});

test('el administrador no puede restablecer su propia contraseña desde la gestion de usuarios', function () {
    Notification::fake();
    $admin = administrador();
    Sanctum::actingAs($admin);

    $this->postJson("/api/users/{$admin->id}/reset-password")->assertUnprocessable();

    Notification::assertNothingSent();
    expect($admin->fresh()->must_change_password)->toBeFalse();
});

test('solo el administrador puede restablecer contraseñas', function () {
    Notification::fake();
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);
    $mesero = User::factory()->create(['role_id' => $role->id]);
    $otro = User::factory()->create(['role_id' => $role->id]);
    Sanctum::actingAs($mesero);

    $this->postJson("/api/users/{$otro->id}/reset-password")->assertForbidden();

    Notification::assertNothingSent();
});

test('con contraseña temporal pendiente solo se permite ver el perfil, cambiar la contraseña y cerrar sesion', function () {
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);
    $user = User::factory()->create(['role_id' => $role->id, 'must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/dishes')
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required');
    $this->getJson('/api/orders')->assertForbidden()->assertJsonPath('code', 'password_change_required');

    $this->getJson('/api/me')->assertOk()->assertJsonPath('must_change_password', true);
    $this->postJson('/api/logout')->assertOk();
});

test('al cambiar la contraseña temporal se levanta la restriccion', function () {
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);
    $user = User::factory()->create([
        'role_id' => $role->id,
        'password' => 'TemporalAbc123',
        'must_change_password' => true,
    ]);
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)->putJson('/api/me/password', [
        'current_password' => 'TemporalAbc123',
        'password' => 'MiPasswordNueva1',
        'password_confirmation' => 'MiPasswordNueva1',
    ])->assertOk();

    expect($user->fresh()->must_change_password)->toBeFalse();

    $this->withToken($token)->getJson('/api/dishes')->assertOk();
});

test('el inicio de sesion con contraseña temporal informa que debe cambiarla', function () {
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);
    $user = User::factory()->create([
        'role_id' => $role->id,
        'password' => 'TemporalAbc123',
        'must_change_password' => true,
    ]);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'TemporalAbc123'])
        ->assertOk()
        ->assertJsonPath('two_factor_required', false)
        ->assertJsonPath('user.must_change_password', true);
});

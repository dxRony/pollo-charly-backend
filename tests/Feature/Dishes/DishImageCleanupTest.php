<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Dish;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeImageStorage;

uses(RefreshDatabase::class);

function iniciarSesionComoAdmin(): void
{
    $role = Role::firstOrCreate(['name' => Role::ADMINISTRADOR]);
    Sanctum::actingAs(User::factory()->create(['role_id' => $role->id]));
}

function crearPlatilloConImagen(?string $imageUrl, string $name = 'Pollo Frito'): Dish
{
    $category = Category::query()->firstOrCreate(['name' => 'Pollos']);

    return Dish::query()->create([
        'category_id' => $category->id,
        'name' => $name,
        'price' => 50,
        'image_url' => $imageUrl,
    ]);
}

function datosActualizacion(Dish $dish, ?string $imageUrl): array
{
    return ['name' => $dish->name, 'category_id' => $dish->category_id, 'price' => 50, 'image_url' => $imageUrl];
}

test('al reemplazar la imagen de un platillo se elimina la anterior', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));
    $storage->seed(FakeImageStorage::url('b'));
    $dish = crearPlatilloConImagen(FakeImageStorage::url('a'));

    $this->putJson("/api/dishes/{$dish->id}", datosActualizacion($dish, FakeImageStorage::url('b')))
        ->assertOk()
        ->assertJsonPath('dish.image_url', FakeImageStorage::url('b'));

    expect($storage->deleted)->toBe([FakeImageStorage::url('a')])
        ->and(array_keys($storage->stored))->toBe([FakeImageStorage::url('b')]);
});

test('al quitar la imagen de un platillo se elimina la anterior', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));
    $dish = crearPlatilloConImagen(FakeImageStorage::url('a'));

    $this->putJson("/api/dishes/{$dish->id}", datosActualizacion($dish, null))->assertOk();

    expect($dish->fresh()->image_url)->toBeNull()
        ->and($storage->deleted)->toBe([FakeImageStorage::url('a')]);
});

test('si la imagen no cambia no se elimina nada', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));
    $dish = crearPlatilloConImagen(FakeImageStorage::url('a'));

    $this->putJson("/api/dishes/{$dish->id}", datosActualizacion($dish, FakeImageStorage::url('a')))->assertOk();

    expect($storage->deleted)->toBeEmpty();
});

test('no se elimina la imagen anterior si otro platillo todavia la usa', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));
    $dish = crearPlatilloConImagen(FakeImageStorage::url('a'));
    crearPlatilloConImagen(FakeImageStorage::url('a'), 'Pollo Asado');

    $this->putJson("/api/dishes/{$dish->id}", datosActualizacion($dish, null))->assertOk();

    expect($storage->deleted)->toBeEmpty();
});

test('un enlace externo antiguo no impide actualizar el platillo', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('b'));
    $dish = crearPlatilloConImagen('https://otro-sitio.test/foto.jpg');

    $this->putJson("/api/dishes/{$dish->id}", datosActualizacion($dish, FakeImageStorage::url('b')))
        ->assertOk()
        ->assertJsonPath('dish.image_url', FakeImageStorage::url('b'));

    expect($storage->deleted)->toBeEmpty();
});

test('un fallo del servicio de imagenes no impide actualizar el platillo', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));
    $storage->deleteFailure = new RuntimeException('Cloudinary no responde');
    $dish = crearPlatilloConImagen(FakeImageStorage::url('a'));

    $this->putJson("/api/dishes/{$dish->id}", datosActualizacion($dish, null))->assertOk();

    expect($dish->fresh()->image_url)->toBeNull();
});

test('el administrador descarta una imagen subida que no se uso', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));

    $this->deleteJson('/api/uploads/dish-image', ['image_url' => FakeImageStorage::url('a')])->assertNoContent();

    expect($storage->deleted)->toBe([FakeImageStorage::url('a')]);
});

test('descartar una imagen que usa un platillo no la elimina', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));
    crearPlatilloConImagen(FakeImageStorage::url('a'));

    $this->deleteJson('/api/uploads/dish-image', ['image_url' => FakeImageStorage::url('a')])->assertNoContent();

    expect($storage->deleted)->toBeEmpty()
        ->and(array_keys($storage->stored))->toBe([FakeImageStorage::url('a')]);
});

test('descartar una imagen inexistente es idempotente', function () {
    iniciarSesionComoAdmin();
    FakeImageStorage::install();

    $this->deleteJson('/api/uploads/dish-image', ['image_url' => FakeImageStorage::url('a')])->assertNoContent();
    $this->deleteJson('/api/uploads/dish-image', ['image_url' => FakeImageStorage::url('a')])->assertNoContent();
});

test('descartar exige una URL https valida', function (array $payload) {
    iniciarSesionComoAdmin();
    FakeImageStorage::install();

    $this->deleteJson('/api/uploads/dish-image', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image_url']);
})->with([
    'vacio' => [[]],
    'no es una url' => [['image_url' => 'no-es-una-url']],
    'http sin cifrar' => [['image_url' => 'http://res.cloudinary.test/a.jpg']],
]);

test('solo el administrador puede descartar imagenes', function () {
    $role = Role::firstOrCreate(['name' => Role::MESERO_CAJERO]);
    Sanctum::actingAs(User::factory()->create(['role_id' => $role->id]));
    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('a'));

    $this->deleteJson('/api/uploads/dish-image', ['image_url' => FakeImageStorage::url('a')])->assertForbidden();

    expect($storage->deleted)->toBeEmpty();
});

test('no se elimina una imagen de otro entorno aunque ningun platillo la use', function () {
    iniciarSesionComoAdmin();
    $storage = FakeImageStorage::install();
    $deOtroEntorno = FakeImageStorage::url('x', 'pollo-charly/production/dishes');
    $storage->seed($deOtroEntorno);

    $this->deleteJson('/api/uploads/dish-image', ['image_url' => $deOtroEntorno])->assertNoContent();

    expect($storage->deleted)->toBeEmpty()
        ->and(array_keys($storage->stored))->toBe([$deOtroEntorno]);
});

<?php

declare(strict_types=1);

use App\Exceptions\ImageUploadFailedException;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeImageStorage;

uses(RefreshDatabase::class);

function actuarComo(string $rol): User
{
    $role = Role::firstOrCreate(['name' => $rol]);
    $user = User::factory()->create(['role_id' => $role->id]);
    Sanctum::actingAs($user);

    return $user;
}

test('el administrador sube una imagen y recibe su URL publica', function () {
    actuarComo(Role::ADMINISTRADOR);
    $storage = FakeImageStorage::install();

    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('pollo.jpg', 800, 600)])
        ->assertCreated()
        ->assertJsonPath('image_url', 'https://res.cloudinary.test/'.Dish::imageFolder().'/foto-1.jpg')
        ->assertJsonStructure(['message', 'image_url']);

    expect($storage->uploads)->toHaveCount(1)
        ->and($storage->uploads[0]['carpeta'])->toBe(Dish::imageFolder());
});

test('acepta imagenes png y webp', function (string $archivo) {
    actuarComo(Role::ADMINISTRADOR);
    FakeImageStorage::install();

    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image($archivo)])
        ->assertCreated();
})->with(['pollo.png', 'pollo.webp', 'pollo.jpeg']);

test('rechaza archivos que no son imagenes permitidas', function (UploadedFile $archivo) {
    actuarComo(Role::ADMINISTRADOR);
    $storage = FakeImageStorage::install();

    $this->postJson('/api/uploads/dish-image', ['image' => $archivo])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    expect($storage->uploads)->toBeEmpty();
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
    'gif' => fn () => UploadedFile::fake()->image('animado.gif'),
    'svg' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'script' => fn () => UploadedFile::fake()->create('virus.php', 10, 'text/x-php'),
]);

test('rechaza imagenes de mas de 2 MB', function () {
    actuarComo(Role::ADMINISTRADOR);
    $storage = FakeImageStorage::install();

    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('grande.jpg')->size(2049)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.image.0', 'La imagen no puede superar los 2 MB.');

    expect($storage->uploads)->toBeEmpty();
});

test('exige enviar una imagen', function () {
    actuarComo(Role::ADMINISTRADOR);
    FakeImageStorage::install();

    $this->postJson('/api/uploads/dish-image', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

test('solo el administrador puede subir imagenes de platillos', function () {
    actuarComo(Role::MESERO_CAJERO);
    $storage = FakeImageStorage::install();

    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('pollo.jpg')])
        ->assertForbidden();

    expect($storage->uploads)->toBeEmpty();
});

test('requiere autenticacion para subir imagenes', function () {
    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('pollo.jpg')])
        ->assertUnauthorized();
});

test('informa un error 502 si el servicio de imagenes falla', function () {
    actuarComo(Role::ADMINISTRADOR);
    FakeImageStorage::install()->uploadFailure = new ImageUploadFailedException;

    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('pollo.jpg')])
        ->assertStatus(502)
        ->assertJsonPath('message', 'No se pudo guardar la imagen. Intenta de nuevo en unos minutos.');
});

test('informa un error 503 si el servicio de imagenes no esta configurado', function () {
    actuarComo(Role::ADMINISTRADOR);
    config(['services.cloudinary.url' => null]);

    $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('pollo.jpg')])
        ->assertStatus(503)
        ->assertJsonPath('message', 'El servicio de almacenamiento de imágenes no está configurado.');
});

test('la URL devuelta se puede guardar como imagen del platillo', function () {
    actuarComo(Role::ADMINISTRADOR);
    FakeImageStorage::install();
    $categoria = Category::query()->create(['name' => 'Pollos']);

    $imageUrl = $this->postJson('/api/uploads/dish-image', ['image' => UploadedFile::fake()->image('pollo.jpg')])
        ->json('image_url');

    $this->postJson('/api/dishes', [
        'name' => 'Pollo Frito Familiar',
        'category_id' => $categoria->id,
        'price' => 125,
        'image_url' => $imageUrl,
    ])->assertCreated()->assertJsonPath('dish.image_url', 'https://res.cloudinary.test/'.Dish::imageFolder().'/foto-1.jpg');
});

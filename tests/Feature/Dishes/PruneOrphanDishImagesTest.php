<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeImageStorage;

uses(RefreshDatabase::class);

function prepararImagenes(): FakeImageStorage
{
    $category = Category::query()->create(['name' => 'Pollos']);
    Dish::query()->create(['category_id' => $category->id, 'name' => 'Pollo', 'price' => 50, 'image_url' => FakeImageStorage::url('en-uso')]);

    $storage = FakeImageStorage::install();
    $storage->seed(FakeImageStorage::url('en-uso'), hoursOld: 72);
    $storage->seed(FakeImageStorage::url('huerfana-antigua'), hoursOld: 48);
    $storage->seed(FakeImageStorage::url('huerfana-reciente'), hoursOld: 1);
    $storage->seed(FakeImageStorage::url('logo', 'pollo-charly/production/dishes'), hoursOld: 500);

    return $storage;
}

test('elimina solo las imagenes huerfanas con mas de 24 horas', function () {
    $storage = prepararImagenes();

    $this->artisan('images:prune-orphans')->assertSuccessful();

    expect($storage->deleted)->toBe([FakeImageStorage::url('huerfana-antigua')])
        ->and(array_keys($storage->stored))->toContain(FakeImageStorage::url('en-uso'), FakeImageStorage::url('huerfana-reciente'), FakeImageStorage::url('logo', 'pollo-charly/production/dishes'));
});

test('en simulacion no elimina nada', function () {
    $storage = prepararImagenes();

    $this->artisan('images:prune-orphans --dry-run')->assertSuccessful();

    expect($storage->deleted)->toBeEmpty()
        ->and($storage->stored)->toHaveCount(4);
});

test('la antiguedad minima es configurable', function () {
    $storage = prepararImagenes();

    $this->artisan('images:prune-orphans --hours=0')->assertSuccessful();

    expect($storage->deleted)->toEqualCanonicalizing([FakeImageStorage::url('huerfana-antigua'), FakeImageStorage::url('huerfana-reciente')])
        ->and(array_keys($storage->stored))->toContain(FakeImageStorage::url('en-uso'), FakeImageStorage::url('logo', 'pollo-charly/production/dishes'));
});

<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Complement;
use App\Models\Dish;
use App\Models\MeasurementUnit;
use App\Models\Supply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeImageStorage;

uses(RefreshDatabase::class);

function platilloPublicado(string $name, array $overrides = []): Dish
{
    $category = Category::query()->firstOrCreate(['name' => 'Pollos']);

    return Dish::query()->create(array_merge([
        'category_id' => $category->id,
        'name' => $name,
        'description' => 'Crujiente y jugoso',
        'price' => 45.5,
        'image_url' => FakeImageStorage::url('pollo'),
        'is_daily_menu' => true,
        'is_active' => true,
    ], $overrides));
}

test('el menu del dia es publico y solo muestra platillos activos marcados para hoy', function () {
    platilloPublicado('Pollo Asado');
    platilloPublicado('Pollo Frito');
    platilloPublicado('Pollo Inactivo', ['is_active' => false]);
    platilloPublicado('Pollo Fuera Del Menu', ['is_daily_menu' => false]);

    $this->getJson('/api/daily-menu')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'Pollo Asado')
        ->assertJsonPath('1.name', 'Pollo Frito');
});

test('el menu del dia expone los datos para mostrar al cliente', function () {
    platilloPublicado('Pollo Frito');

    $this->getJson('/api/daily-menu')
        ->assertOk()
        ->assertExactJsonStructure([
            '*' => ['id', 'name', 'category' => ['id', 'name'], 'description', 'price', 'image_url', 'complements'],
        ])
        ->assertJsonPath('0.price', 45.5)
        ->assertJsonPath('0.image_url', FakeImageStorage::url('pollo'));
});

test('el menu del dia publico no filtra la receta ni datos internos', function () {
    $dish = platilloPublicado('Pollo Frito');
    $unit = MeasurementUnit::query()->create(['name' => 'Libra', 'abbreviation' => 'lb']);
    $supply = Supply::query()->create([
        'measurement_unit_id' => $unit->id,
        'code' => 'POL-001',
        'name' => 'Pechuga secreta',
        'current_stock' => 100,
        'minimum_stock' => 10,
        'unit_cost' => 12,
        'is_active' => true,
    ]);
    $dish->recipes()->create(['supply_id' => $supply->id, 'required_quantity' => 2]);

    $response = $this->getJson('/api/daily-menu')->assertOk();

    expect($response->getContent())
        ->not->toContain('recipes')
        ->not->toContain('Pechuga secreta')
        ->not->toContain('POL-001')
        ->not->toContain('is_active')
        ->not->toContain('is_daily_menu');
});

test('el menu del dia solo muestra los complementos activos', function () {
    $dish = platilloPublicado('Pollo Frito');
    $activo = Complement::query()->create(['name' => 'Papas fritas', 'extra_price' => 15, 'is_active' => true]);
    $inactivo = Complement::query()->create(['name' => 'Ensalada agotada', 'extra_price' => 10, 'is_active' => false]);
    $dish->complements()->attach([$activo->id, $inactivo->id]);

    $this->getJson('/api/daily-menu')
        ->assertOk()
        ->assertJsonCount(1, '0.complements')
        ->assertJsonPath('0.complements.0.name', 'Papas fritas')
        ->assertJsonPath('0.complements.0.extra_price', 15);
});

test('sin platillos publicados devuelve una lista vacia', function () {
    $this->getJson('/api/daily-menu')->assertOk()->assertExactJson([]);
});

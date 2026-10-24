<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Models\Category;
use App\Models\Complement;
use App\Models\DeliveryDay;
use App\Models\Dish;
use App\Models\MeasurementUnit;
use App\Models\Supply;
use App\Support\Demo\DemoApi;
use App\Support\Demo\DemoCatalog;
use App\Support\Demo\DemoContext;
use Closure;

/** Carga los datos maestros (insumos con su stock inicial, complementos, platillos, mesas, proveedores y menú del día). */
final class SeedDemoCatalogAction
{
    public function __construct(private readonly DemoApi $api) {}

    public function handle(DemoContext $context, Closure $progress): void
    {
        $admin = $context->admin;

        $units = MeasurementUnit::query()->pluck('id', 'abbreviation');

        foreach (DemoCatalog::supplies() as $code => $supply) {
            $this->api->call($admin, 'POST', '/supplies', [
                'name' => $supply['name'],
                'code' => $code,
                'measurement_unit_id' => $units[$supply['unit']],
                'minimum_stock' => $supply['minimum'],
                'unit_cost' => $supply['cost'],
            ]);
        }

        $supplyIds = Supply::query()->pluck('id', 'code');

        // El stock inicial entra como una compra, para que quede registrado en el historial de movimientos.
        foreach (DemoCatalog::supplies() as $code => $supply) {
            $this->api->call($admin, 'POST', '/inventory-movements', [
                'supply_id' => $supplyIds[$code],
                'type' => 'compra_entrada',
                'quantity' => $supply['initial'],
                'reason' => 'Inventario inicial',
            ]);
        }
        $progress(sprintf('Insumos cargados con su stock inicial: %d', count($supplyIds)));

        foreach (DemoCatalog::complements() as $name => $complement) {
            $this->api->call($admin, 'POST', '/complements', [
                'name' => $name,
                'description' => $complement['description'],
                'extra_price' => $complement['price'],
                'supplies' => collect($complement['supplies'])
                    ->map(fn (float $quantity, string $code) => ['supply_id' => $supplyIds[$code], 'required_quantity' => $quantity])
                    ->values()
                    ->all(),
            ]);
        }
        $complementIds = Complement::query()->pluck('id')->all();

        $categories = Category::query()->pluck('id', 'name');

        foreach (DemoCatalog::dishes() as $name => $dish) {
            $this->api->call($admin, 'POST', '/dishes', [
                'name' => $name,
                'category_id' => $categories[$dish['category']],
                'description' => $dish['description'],
                'price' => $dish['price'],
                'is_active' => true,
                'recipes' => collect($dish['recipe'])
                    ->map(fn (float|int $quantity, string $code) => ['supply_id' => $supplyIds[$code], 'required_quantity' => $quantity])
                    ->values()
                    ->all(),
                'complements' => $dish['with_sides'] ? $complementIds : [],
            ]);
        }
        $progress(sprintf('Complementos: %d · Platillos: %d', count($complementIds), Dish::query()->count()));

        foreach (DemoCatalog::tables() as $number => $capacity) {
            $this->api->call($admin, 'POST', '/restaurant-tables', ['number' => $number, 'capacity' => $capacity]);
        }

        $days = DeliveryDay::query()->pluck('id', 'name');

        foreach (DemoCatalog::suppliers() as $supplier) {
            $this->api->call($admin, 'POST', '/suppliers', [
                'company_name' => $supplier['company'],
                'contact_name' => $supplier['contact'],
                'phone' => $supplier['phone'],
                'email' => $supplier['email'],
                'address' => $supplier['address'],
                'delivery_day_ids' => collect($supplier['days'])->map(fn (string $day) => $days[$day])->values()->all(),
                'supplies' => collect($supplier['supplies'])
                    ->map(fn (float $price, string $code) => ['supply_id' => $supplyIds[$code], 'agreed_price' => $price])
                    ->values()
                    ->all(),
            ]);
        }
        $progress(sprintf('Mesas: %d · Proveedores: %d', count(DemoCatalog::tables()), count(DemoCatalog::suppliers())));
    }

    /**
     * Publica en el menú del día los platillos definidos en el catálogo. Si el sistema rechaza el conjunto completo
     * (por ejemplo, por falta de insumos de alguno), se publican de uno en uno los que sí se puedan.
     */
    public function publishDailyMenu(DemoContext $context): void
    {
        $dishIds = Dish::query()->whereIn('name', DemoCatalog::dailyMenu())->pluck('id')->all();

        [$status] = $this->api->send($context->admin, 'PUT', '/daily-menu', ['dish_ids' => $dishIds]);

        if ($status < 400) {
            return;
        }

        foreach ($dishIds as $dishId) {
            $this->api->send($context->admin, 'PATCH', "/dishes/{$dishId}/daily-menu", ['is_daily_menu' => true]);
        }
    }
}

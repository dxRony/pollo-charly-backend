<?php

declare(strict_types=1);

use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supply;
use App\Models\User;
use App\Support\Demo\DemoRandom;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    User::factory()->create([
        'name' => 'Administrador Real',
        'email' => 'admin@pollocharly.test',
        'role_id' => Role::query()->where('name', Role::ADMINISTRADOR)->value('id'),
        'is_active' => true,
    ]);
});

function cargarDemostracion(int $days = 3): string
{
    expect(Artisan::call('demo:seed', ['--days' => $days]))->toBe(0);

    return Artisan::output();
}

test('carga un catalogo completo y un historial de ventas', function () {
    cargarDemostracion();

    expect(Supply::count())->toBe(18)
        ->and(Dish::count())->toBe(13)
        ->and(RestaurantTable::count())->toBe(8)
        ->and(Order::count())->toBeGreaterThan(10)
        ->and(Sale::count())->toBeGreaterThan(10)
        ->and(Dish::where('is_daily_menu', true)->count())->toBeGreaterThan(0);
});

test('las ventas y comandas quedan en el pasado y dentro del horario de atencion', function () {
    cargarDemostracion();

    expect(Sale::where('created_at', '>', now())->count())->toBe(0)
        ->and(InventoryMovement::where('created_at', '>', now())->count())->toBe(0);

    Sale::all()->each(function (Sale $sale) {
        $hour = (float) $sale->created_at->format('G') + ((int) $sale->created_at->format('i')) / 60;

        expect($hour)->toBeGreaterThanOrEqual(8.5)->toBeLessThanOrEqual(18.0);
    });
});

test('el inventario es coherente: sin stock negativo y cada movimiento continua al anterior', function () {
    cargarDemostracion(7);

    expect(Supply::where('current_stock', '<', 0)->count())->toBe(0);

    foreach (Supply::all() as $supply) {
        $previous = 0.0;

        // Los ajustes pendientes o rechazados no modifican el stock; el aprobado sí y forma parte de la cadena.
        $movements = InventoryMovement::query()
            ->where('supply_id', $supply->id)
            ->where(fn ($query) => $query->whereNull('adjustment_status_type_id')
                ->orWhereHas('adjustmentStatus', fn ($status) => $status->where('name', 'aprobado')))
            ->orderBy('created_at')->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            expect((float) $movement->previous_stock)->toEqualWithDelta($previous, 0.011);
            $previous = (float) $movement->new_stock;
        }

        expect($previous)->toEqualWithDelta((float) $supply->current_stock, 0.011);
    }
});

test('no deja comandas abiertas ni mesas ocupadas y cada comanda entregada tiene su venta', function () {
    cargarDemostracion();

    expect(Order::whereHas('status', fn ($query) => $query->whereIn('name', ['pendiente', 'en_preparacion', 'lista']))->count())->toBe(0)
        ->and(RestaurantTable::whereHas('status', fn ($query) => $query->where('name', '!=', 'disponible'))->count())->toBe(0)
        ->and(Order::whereHas('status', fn ($query) => $query->where('name', 'entregada'))->count())->toBe(Sale::count());

    Sale::with('order.items.complements')->get()->each(function (Sale $sale) {
        $subtotal = $sale->order->items->sum(fn ($item) => (float) $item->subtotal + (float) $item->complements->sum('subtotal'));

        expect((float) $sale->total)->toEqualWithDelta($subtotal - (float) $sale->discount + (float) $sale->tax, 0.011);
    });
});

test('deja tareas pendientes para mostrar los flujos de aprobacion', function () {
    cargarDemostracion();

    expect(DB::table('purchase_requests')->join('purchase_request_statuses', 'purchase_request_statuses.id', '=', 'purchase_requests.purchase_request_status_id')->where('purchase_request_statuses.name', 'pendiente')->count())->toBe(1)
        ->and(DB::table('purchase_orders')->join('purchase_order_statuses', 'purchase_order_statuses.id', '=', 'purchase_orders.purchase_order_status_id')->where('purchase_order_statuses.name', 'solicitada')->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('supply_alerts')->join('alert_statuses', 'alert_statuses.id', '=', 'supply_alerts.alert_status_id')->where('alert_statuses.name', 'pending')->count())->toBeGreaterThanOrEqual(3);
});

test('crea usuarios de demostracion con contrasenas que funcionan y sin cambio obligatorio', function () {
    $output = cargarDemostracion(3);

    preg_match('/mesero1@demo\.pollocharly\.test\s*\|\s*Mesero\/Cajero\s*\|\s*(\S+)\s*\|/', $output, $matches);
    expect($matches)->toHaveCount(2);

    $user = User::where('email', 'mesero1@demo.pollocharly.test')->firstOrFail();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check($matches[1], $user->password))->toBeTrue();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => $matches[1]])
        ->assertOk()
        ->assertJsonPath('two_factor_required', false);
});

test('no deja tokens temporales ni la fecha del sistema alterada', function () {
    cargarDemostracion(3);

    expect(PersonalAccessToken::where('name', 'demo-seed')->count())->toBe(0)
        ->and(Carbon::hasTestNow())->toBeFalse();
});

test('rechaza cargar la demostracion sobre una base que ya tiene datos', function () {
    cargarDemostracion();

    $orders = Order::count();

    expect(Artisan::call('demo:seed', ['--days' => 3]))->toBe(1)
        ->and(Artisan::output())->toContain('ya contiene datos')
        ->and(Order::count())->toBe($orders);
});

test('valida el rango de dias', function (int $days) {
    expect(Artisan::call('demo:seed', ['--days' => $days]))->toBe(1)
        ->and(Order::count())->toBe(0);
})->with([0, 91]);

test('la misma semilla produce siempre los mismos datos', function () {
    $first = new DemoRandom(7);
    $second = new DemoRandom(7);

    $sequence = fn (DemoRandom $random) => [$random->int(1, 1000), $random->chance(50), $random->weighted(['a' => 1, 'b' => 5, 'c' => 3]), $random->pick([10, 20, 30])];

    expect($sequence($first))->toBe($sequence($second));
});

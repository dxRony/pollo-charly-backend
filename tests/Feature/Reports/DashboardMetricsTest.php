<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderType;
use App\Models\PaymentMethod;
use App\Models\ReceiptType;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->adminUser = User::factory()->create([
        'role_id' => Role::where('name', 'Administrador')->first()->id,
        'email' => 'admin_dashboard@pollocharly.com',
    ]);

    $this->waiterUser = User::factory()->create([
        'role_id' => Role::where('name', 'Mesero/Cajero')->first()->id,
        'email' => 'mesero_dashboard@pollocharly.com',
    ]);

    $this->saleSequence = 0;

    $this->createSale = function (string $paymentMethod, float $total, ?Carbon $createdAt = null): Sale {
        $this->saleSequence++;

        $order = Order::create([
            'code' => sprintf('COM-DASH-%04d', $this->saleSequence),
            'waiter_user_id' => $this->waiterUser->id,
            'order_type_id' => OrderType::where('name', OrderType::PARA_LLEVAR)->first()->id,
            'order_status_id' => OrderStatus::where('name', OrderStatus::PENDIENTE)->first()->id,
        ]);

        $sale = Sale::create([
            'order_id' => $order->id,
            'cashier_user_id' => $this->waiterUser->id,
            'receipt_type_id' => ReceiptType::where('name', ReceiptType::TICKET)->first()->id,
            'payment_method_id' => PaymentMethod::where('name', $paymentMethod)->first()->id,
            'sale_status_id' => SaleStatus::where('name', SaleStatus::COMPLETADA)->first()->id,
            'receipt_number' => sprintf('VTA-DASH-%04d', $this->saleSequence),
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'received_amount' => $total,
            'change_amount' => 0,
        ]);

        if ($createdAt !== null) {
            $sale->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $sale;
    };
});

test('el dashboard desglosa las ventas del periodo por metodo de pago', function () {
    ($this->createSale)(PaymentMethod::EFECTIVO, 100.00);
    ($this->createSale)(PaymentMethod::EFECTIVO, 50.50);
    ($this->createSale)(PaymentMethod::TARJETA, 300.00);

    $response = $this->actingAs($this->adminUser)->getJson('/api/reports/dashboard');

    $response->assertOk()->assertJsonStructure([
        'sales_by_payment_method' => [['payment_method', 'total', 'count']],
    ]);

    $methods = collect($response->json('sales_by_payment_method'));

    expect($methods->pluck('payment_method')->all())->toBe([PaymentMethod::TARJETA, PaymentMethod::EFECTIVO]);

    $cash = $methods->firstWhere('payment_method', PaymentMethod::EFECTIVO);
    expect((float) $cash['total'])->toBe(150.5)
        ->and($cash['count'])->toBe(2);

    $card = $methods->firstWhere('payment_method', PaymentMethod::TARJETA);
    expect((float) $card['total'])->toBe(300.0)
        ->and($card['count'])->toBe(1);

    expect((float) $methods->sum('total'))->toBe((float) $response->json('kpis.total_sales'));
});

test('el desglose por metodo de pago respeta el rango de fechas y omite metodos sin ventas', function () {
    ($this->createSale)(PaymentMethod::EFECTIVO, 80.00, Carbon::now()->subDays(60));
    ($this->createSale)(PaymentMethod::TRANSFERENCIA, 120.00, Carbon::now()->subDays(2));

    $response = $this->actingAs($this->adminUser)->getJson('/api/reports/dashboard');

    $methods = collect($response->json('sales_by_payment_method'));

    expect($methods)->toHaveCount(1)
        ->and($methods->first()['payment_method'])->toBe(PaymentMethod::TRANSFERENCIA)
        ->and((float) $methods->first()['total'])->toBe(120.0);

    $custom = $this->actingAs($this->adminUser)->getJson('/api/reports/dashboard?'.http_build_query([
        'date_from' => Carbon::now()->subDays(90)->toDateString(),
        'date_to' => Carbon::now()->toDateString(),
    ]));

    expect(collect($custom->json('sales_by_payment_method')))->toHaveCount(2);
});

test('sin ventas el desglose por metodo de pago llega vacio', function () {
    $response = $this->actingAs($this->adminUser)->getJson('/api/reports/dashboard');

    $response->assertOk();
    expect($response->json('sales_by_payment_method'))->toBe([]);
});

test('el dashboard sigue reservado al administrador', function () {
    $this->actingAs($this->waiterUser)->getJson('/api/reports/dashboard')->assertForbidden();
});

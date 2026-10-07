<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AlertOrigin;
use App\Models\AlertStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\MeasurementUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseRequestStatus;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->adminRole = Role::where('name', 'Administrador')->first();
    $this->waiterRole = Role::where('name', 'Mesero/Cajero')->first();

    $this->adminUser = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'email' => 'admin_test@pollocharly.com',
    ]);

    $this->waiterUser = User::factory()->create([
        'role_id' => $this->waiterRole->id,
        'email' => 'mesero_test@pollocharly.com',
    ]);

    $this->unit = MeasurementUnit::where('abbreviation', 'kg')->first();

    $this->supply = Supply::create([
        'code' => 'INS-POLLO-1',
        'name' => 'Pechuga de Pollo Fresca',
        'measurement_unit_id' => $this->unit->id,
        'minimum_stock' => 10,
        'current_stock' => 4,
        'unit_cost' => 30.00,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_name' => 'Distribuidora Avícola S.A.',
        'contact_name' => 'Juan Pérez',
        'phone' => '7890-1234',
        'email' => 'ventas@avicola.com',
        'address' => 'Zona 12, Bodega 4',
        'is_active' => true,
    ]);
});

test('Scenario 1: Revisar una solicitud generada por una alerta atendida', function () {
    $origin = AlertOrigin::where('name', AlertOrigin::AUTOMATIC)->first();
    $pendingStatus = AlertStatus::where('name', AlertStatus::PENDING)->first();

    $alert = SupplyAlert::create([
        'supply_id' => $this->supply->id,
        'alert_origin_id' => $origin->id,
        'alert_status_id' => $pendingStatus->id,
        'user_id' => $this->adminUser->id,
        'notes' => 'Stock por debajo del mínimo permitido.',
    ]);

    // Al atender la alerta, se genera automáticamente la solicitud de compra
    $responseAttend = $this->actingAs($this->adminUser)
        ->postJson("/api/supply-alerts/{$alert->id}/attend");

    $responseAttend->assertOk();

    $alert->refresh();
    expect($alert->purchase_request_id)->not->toBeNull();

    // La administradora revisa las solicitudes de compra y visualiza cantidades sugeridas
    $response = $this->actingAs($this->adminUser)
        ->getJson("/api/purchase-requests/{$alert->purchase_request_id}");

    $response->assertOk()
        ->assertJsonPath('purchase_request.status', 'pendiente')
        ->assertJsonCount(1, 'purchase_request.items');

    $item = $response->json('purchase_request.items.0');
    expect($item['supply_id'])->toBe($this->supply->id);
    expect((float) $item['suggested_quantity'])->toBeGreaterThan(0.0);
    expect($item['approved_quantity'])->toBeNull();
});

test('Scenario 2: Aprobar la solicitud y registrar la compra calculando total automaticamente', function () {
    Notification::fake();

    $pendingPrStatus = PurchaseRequestStatus::where('name', PurchaseRequestStatus::PENDIENTE)->first();

    $purchaseRequest = PurchaseRequest::create([
        'requester_user_id' => $this->adminUser->id,
        'purchase_request_status_id' => $pendingPrStatus->id,
        'reason' => 'Solicitud de reposición urgente',
    ]);

    PurchaseRequestItem::create([
        'purchase_request_id' => $purchaseRequest->id,
        'supply_id' => $this->supply->id,
        'suggested_quantity' => 20,
    ]);

    // La administradora aprueba la solicitud seleccionando un proveedor y cantidades/precios
    $response = $this->actingAs($this->adminUser)
        ->postJson("/api/purchase-requests/{$purchaseRequest->id}/approve", [
            'supplier_id' => $this->supplier->id,
            'expected_date' => '2026-10-20',
            'items' => [
                [
                    'supply_id' => $this->supply->id,
                    'quantity' => 15,
                    'unit_price' => 32.50,
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('purchase_request.status', 'aprobada')
        ->assertJsonPath('purchase_order.supplier_id', $this->supplier->id)
        ->assertJsonPath('purchase_order.total', 487.50); // 15 * 32.50

    $purchaseRequest->refresh();
    expect($purchaseRequest->status->name)->toBe('aprobada');
    expect((float) $purchaseRequest->items->first()->approved_quantity)->toBe(15.0);

    // Se verifica que la orden de compra quedó asociada al proveedor y en estado solicitada
    $order = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->first();
    expect($order)->not->toBeNull();
    expect($order->status->name)->toBe('solicitada');
    expect((float) $order->total)->toBe(487.50);
});

test('Scenario 3: Rechazar la solicitud sin comprar', function () {
    $pendingPrStatus = PurchaseRequestStatus::where('name', PurchaseRequestStatus::PENDIENTE)->first();

    $purchaseRequest = PurchaseRequest::create([
        'requester_user_id' => $this->adminUser->id,
        'purchase_request_status_id' => $pendingPrStatus->id,
        'reason' => 'Revisión periódica de inventario',
    ]);

    PurchaseRequestItem::create([
        'purchase_request_id' => $purchaseRequest->id,
        'supply_id' => $this->supply->id,
        'suggested_quantity' => 10,
    ]);

    // La administradora rechaza la solicitud
    $response = $this->actingAs($this->adminUser)
        ->postJson("/api/purchase-requests/{$purchaseRequest->id}/reject", [
            'reason' => 'Contamos con stock suficiente hasta la próxima semana.',
        ]);

    $response->assertOk()
        ->assertJsonPath('purchase_request.status', 'rechazada_sin_comprar');

    $purchaseRequest->refresh();
    expect($purchaseRequest->status->name)->toBe('rechazada_sin_comprar');

    // No se generó ninguna orden de compra
    expect(PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->count())->toBe(0);
});

test('Scenario 4: Confirmar la recepcion de una compra actualiza existencias e inventario', function () {
    $solicitadaStatus = PurchaseOrderStatus::where('name', PurchaseOrderStatus::SOLICITADA)->first();

    $order = PurchaseOrder::create([
        'code' => 'OC-20261007-0099',
        'supplier_id' => $this->supplier->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $solicitadaStatus->id,
        'total' => 300.00,
        'expected_date' => '2026-10-10',
    ]);

    $order->items()->create([
        'supply_id' => $this->supply->id,
        'ordered_quantity' => 10,
        'unit_price' => 30.00,
        'subtotal' => 300.00,
    ]);

    $stockInicial = (float) $this->supply->current_stock; // 4

    // Confirmar recepción de la compra
    $response = $this->actingAs($this->adminUser)
        ->postJson("/api/purchase-orders/{$order->id}/receive", [
            'received_date' => '2026-10-11',
            'notes' => 'Entrega recibida completa y en orden.',
        ]);

    $response->assertOk()
        ->assertJsonPath('purchase_order.status', 'recibida_completa');

    // Actualiza existencias del insumo
    $this->supply->refresh();
    expect((float) $this->supply->current_stock)->toBe($stockInicial + 10.0);

    // Actualiza historial de movimientos con tipo compra_entrada
    $movement = InventoryMovement::where('purchase_order_id', $order->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->movementType->name)->toBe('compra_entrada');
    expect((float) $movement->quantity)->toBe(10.0);
    expect((float) $movement->previous_stock)->toBe($stockInicial);
    expect((float) $movement->new_stock)->toBe($stockInicial + 10.0);
});

test('Scenario 5: Registrar una compra manual sin alerta previa', function () {
    Notification::fake();

    $payload = [
        'supplier_id' => $this->supplier->id,
        'expected_date' => '2026-10-25',
        'items' => [
            [
                'supply_id' => $this->supply->id,
                'ordered_quantity' => 30,
                'unit_price' => 28.00,
            ],
        ],
    ];

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/purchase-orders', $payload);

    $response->assertCreated()
        ->assertJsonPath('purchase_order.supplier_id', $this->supplier->id)
        ->assertJsonPath('purchase_order.total', 840) // 30 * 28.00
        ->assertJsonPath('purchase_order.status', 'solicitada');

    $orderId = $response->json('purchase_order.id');
    $order = PurchaseOrder::find($orderId);
    expect($order)->not->toBeNull();
    expect($order->purchase_request_id)->toBeNull();
    expect((float) $order->total)->toBe(840.00);
});

test('Rechaza aprobacion con proveedor inactivo', function () {
    $inactiveSupplier = Supplier::create([
        'company_name' => 'Proveedor Inactivo',
        'phone' => '1111-2222',
        'is_active' => false,
    ]);

    $pendingPrStatus = PurchaseRequestStatus::where('name', PurchaseRequestStatus::PENDIENTE)->first();

    $pr = PurchaseRequest::create([
        'requester_user_id' => $this->adminUser->id,
        'purchase_request_status_id' => $pendingPrStatus->id,
        'reason' => 'Prueba con proveedor inactivo',
    ]);

    $pr->items()->create([
        'supply_id' => $this->supply->id,
        'suggested_quantity' => 10,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson("/api/purchase-requests/{$pr->id}/approve", [
            'supplier_id' => $inactiveSupplier->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'El proveedor seleccionado está inactivo o no existe.');
});

test('Solo administradores pueden acceder al modulo de compras', function () {
    $responseRequests = $this->actingAs($this->waiterUser)
        ->getJson('/api/purchase-requests');
    $responseRequests->assertForbidden();

    $responseOrders = $this->actingAs($this->waiterUser)
        ->getJson('/api/purchase-orders');
    $responseOrders->assertForbidden();
});

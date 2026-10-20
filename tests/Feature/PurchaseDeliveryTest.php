<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeliveryIncident;
use App\Models\DeliveryIncidentStatus;
use App\Models\DeliveryIncidentType;
use App\Models\InventoryMovement;
use App\Models\MeasurementUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\User;
use App\Notifications\DeliveryIncidentNotification;
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
    $this->cookRole = Role::where('name', 'Cocinero')->first();

    $this->adminUser = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'email' => 'admin_hu14@pollocharly.com',
    ]);

    $this->waiterUser = User::factory()->create([
        'role_id' => $this->waiterRole->id,
        'email' => 'mesero_hu14@pollocharly.com',
    ]);

    $this->cookUser = User::factory()->create([
        'role_id' => $this->cookRole->id,
        'email' => 'cocinero_hu14@pollocharly.com',
    ]);

    $this->unit = MeasurementUnit::where('abbreviation', 'kg')->first();

    $this->supply = Supply::create([
        'code' => 'INS-POLLO-01',
        'name' => 'Pierna y Muslo de Pollo',
        'measurement_unit_id' => $this->unit->id,
        'minimum_stock' => 15,
        'current_stock' => 5,
        'unit_cost' => 25.00,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_name' => 'Avícola Santa Clara',
        'contact_name' => 'Carlos Morales',
        'phone' => '4444-5555',
        'email' => 'carlos@santaclara.com',
        'address' => 'Carretera al Salvador km 14',
        'is_active' => true,
    ]);

    $solicitadaStatus = PurchaseOrderStatus::where('name', PurchaseOrderStatus::SOLICITADA)->first();

    $this->order = PurchaseOrder::create([
        'code' => 'OC-20261007-0014',
        'supplier_id' => $this->supplier->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $solicitadaStatus->id,
        'total' => 250.00,
        'expected_date' => now()->toDateString(),
    ]);

    $this->order->items()->create([
        'supply_id' => $this->supply->id,
        'ordered_quantity' => 10,
        'unit_price' => 25.00,
        'subtotal' => 250.00,
    ]);
});

test('HU-14 Scenario 1: Mesero confirma recepcion conforme y actualiza existencias e inventario', function () {
    $stockInicial = (float) $this->supply->current_stock; // 5

    // Mesero confirma la entrega conforme
    $response = $this->actingAs($this->waiterUser)
        ->postJson("/api/purchase-orders/{$this->order->id}/receive", [
            'received_date' => now()->toDateString(),
            'notes' => 'Productos verificados en peso y calidad. Conforme.',
        ]);

    $response->assertOk()
        ->assertJsonPath('purchase_order.status', 'recibida_completa');

    // Verifica que existencias aumentaron
    $this->supply->refresh();
    expect((float) $this->supply->current_stock)->toBe($stockInicial + 10.0);

    // Verifica movimiento de inventario compra_entrada (HU-10)
    $movement = InventoryMovement::where('purchase_order_id', $this->order->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->movementType->name)->toBe('compra_entrada');
    expect($movement->user_id)->toBe($this->waiterUser->id);
    expect((float) $movement->quantity)->toBe(10.0);
    expect((float) $movement->previous_stock)->toBe($stockInicial);
    expect((float) $movement->new_stock)->toBe($stockInicial + 10.0);
});

test('HU-14 Scenario 2: Mesero reporta recepcion no conforme y notifica a la administradora', function () {
    Notification::fake();

    $incidentType = DeliveryIncidentType::where('name', 'diferencia_peso')->first()
        ?? DeliveryIncidentType::first();

    $response = $this->actingAs($this->waiterUser)
        ->postJson("/api/purchase-orders/{$this->order->id}/incident", [
            'delivery_incident_type_id' => $incidentType->id,
            'description' => 'Faltaron 2 kg de pierna y muslo en el empaque recibido.',
            'evidence_path' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg',
        ]);

    $response->assertCreated()
        ->assertJsonPath('incident.delivery_incident_type_id', $incidentType->id)
        ->assertJsonPath('incident.status', 'reportada')
        ->assertJsonPath('purchase_order.status', 'recibida_con_incidencia');

    // La orden queda marcada como recibida_con_incidencia
    $this->order->refresh();
    expect($this->order->status->name)->toBe(PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA);

    // Se registró la incidencia en base de datos
    $incident = DeliveryIncident::where('purchase_order_id', $this->order->id)->first();
    expect($incident)->not->toBeNull();
    expect($incident->receiving_user_id)->toBe($this->waiterUser->id);
    expect($incident->description)->toContain('Faltaron 2 kg');

    // Se notifica por correo a la administradora
    Notification::assertSentTo(
        $this->adminUser,
        DeliveryIncidentNotification::class,
        function ($notification) use ($incident) {
            return $notification->incident->id === $incident->id;
        }
    );
});

test('HU-14 Scenario 3: Proveedor corrige o repone productos y mesero recibe la entrega corregida', function () {
    $stockInicial = (float) $this->supply->current_stock; // 5

    $incidentType = DeliveryIncidentType::first();
    $reportadaStatus = DeliveryIncidentStatus::where('name', 'reportada')->first();
    $recibidaIncidenciaOrderStatus = PurchaseOrderStatus::where('name', PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA)->first();

    // Crear orden con estado recibida_con_incidencia y una incidencia reportada
    $this->order->update([
        'purchase_order_status_id' => $recibidaIncidenciaOrderStatus->id,
    ]);

    $incident = DeliveryIncident::create([
        'supplier_id' => $this->supplier->id,
        'purchase_order_id' => $this->order->id,
        'receiving_user_id' => $this->waiterUser->id,
        'delivery_incident_type_id' => $incidentType->id,
        'delivery_incident_status_id' => $reportadaStatus->id,
        'description' => 'Producto incompleto originalmente',
    ]);

    // Proveedor corrige/repone productos y mesero revisa y confirma entrega corregida
    $response = $this->actingAs($this->waiterUser)
        ->postJson("/api/purchase-orders/{$this->order->id}/receive", [
            'received_date' => now()->toDateString(),
            'notes' => 'Proveedor entregó reposición de los productos faltantes. Todo conforme.',
        ]);

    $response->assertOk()
        ->assertJsonPath('purchase_order.status', 'recibida_completa');

    // Existencias se actualizan en almacén
    $this->supply->refresh();
    expect((float) $this->supply->current_stock)->toBe($stockInicial + 10.0);

    // La incidencia asociada se resuelve automáticamente
    $incident->refresh();
    expect($incident->status->name)->toBe('resuelta');

    // Se registra movimiento de inventario
    $movement = InventoryMovement::where('purchase_order_id', $this->order->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->movementType->name)->toBe('compra_entrada');
});

test('HU-14 Permisos: Mesero puede consultar y recibir compras, Cocinero no tiene permiso', function () {
    // Mesero puede listar órdenes
    $responseWaiterList = $this->actingAs($this->waiterUser)
        ->getJson('/api/purchase-orders');
    $responseWaiterList->assertOk();

    // Mesero puede ver detalle de orden
    $responseWaiterShow = $this->actingAs($this->waiterUser)
        ->getJson("/api/purchase-orders/{$this->order->id}");
    $responseWaiterShow->assertOk();

    // Cocinero no puede listar ni ver órdenes
    $responseCookList = $this->actingAs($this->cookUser)
        ->getJson('/api/purchase-orders');
    $responseCookList->assertForbidden();

    $responseCookReceive = $this->actingAs($this->cookUser)
        ->postJson("/api/purchase-orders/{$this->order->id}/receive", []);
    $responseCookReceive->assertForbidden();
});

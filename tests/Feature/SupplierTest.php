<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeliveryDay;
use App\Models\DeliveryIncident;
use App\Models\DeliveryIncidentStatus;
use App\Models\DeliveryIncidentType;
use App\Models\MeasurementUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'code' => 'INS-TEST-1',
        'name' => 'Pechuga de Pollo Fresca',
        'measurement_unit_id' => $this->unit->id,
        'minimum_stock' => 10,
        'unit_cost' => 30.00,
        'is_active' => true,
    ]);
});

test('Scenario: Buscar un proveedor ya registrado y consultar contacto, productos, precios e historial', function () {
    $supplier = Supplier::create([
        'company_name' => 'Distribuidora Avícola',
        'contact_name' => 'Carlos López',
        'phone' => '7777-1111',
        'email' => 'ventas@avicola.com',
        'address' => 'Zona 12, Ciudad Central',
        'is_active' => true,
    ]);

    $lunes = DeliveryDay::where('name', 'Lunes')->first();
    $miercoles = DeliveryDay::where('name', 'Miércoles')->first();
    $supplier->deliveryDays()->sync([$lunes->id, $miercoles->id]);

    $supplier->supplierSupplies()->create([
        'supply_id' => $this->supply->id,
        'agreed_price' => 28.50,
    ]);

    $poStatusCompleted = PurchaseOrderStatus::where('name', 'recibida_completa')->first();
    $po = PurchaseOrder::create([
        'code' => 'OC-2026-TEST-01',
        'supplier_id' => $supplier->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $poStatusCompleted->id,
        'total' => 570.00,
        'expected_date' => now()->toDateString(),
        'received_date' => now()->toDateString(),
    ]);

    $incidentType = DeliveryIncidentType::where('name', 'peso_incompleto')->first();
    $incidentStatus = DeliveryIncidentStatus::where('name', 'reportada')->first();
    DeliveryIncident::create([
        'purchase_order_id' => $po->id,
        'supplier_id' => $supplier->id,
        'receiving_user_id' => $this->waiterUser->id,
        'delivery_incident_type_id' => $incidentType->id,
        'delivery_incident_status_id' => $incidentStatus->id,
        'description' => 'Faltaron 2 kg de pechuga.',
    ]);

    $responseSearch = $this->actingAs($this->adminUser)
        ->getJson('/api/suppliers?search=Distribuidora Avícola');

    $responseSearch->assertOk()
        ->assertJsonFragment(['company_name' => 'Distribuidora Avícola']);

    $responseDetail = $this->actingAs($this->adminUser)
        ->getJson("/api/suppliers/{$supplier->id}");

    $responseDetail->assertOk()
        ->assertJsonPath('data.company_name', 'Distribuidora Avícola')
        ->assertJsonPath('data.contact_name', 'Carlos López')
        ->assertJsonPath('data.phone', '7777-1111')
        ->assertJsonPath('data.email', 'ventas@avicola.com')
        ->assertJsonFragment(['agreed_price' => 28.5])
        ->assertJsonFragment(['code' => 'OC-2026-TEST-01'])
        ->assertJsonFragment(['description' => 'Faltaron 2 kg de pechuga.']);
});

test('Scenario: Registrar un proveedor nuevo con contacto, productos, precios y dias de entrega', function () {
    $lunes = DeliveryDay::where('name', 'Lunes')->first();
    $viernes = DeliveryDay::where('name', 'Viernes')->first();

    $payload = [
        'company_name' => 'Granja San Rafael',
        'contact_name' => 'Marta Soto',
        'phone' => '4444-2222',
        'email' => 'contacto@sanrafael.com',
        'address' => 'Carretera Norte Km 15',
        'is_active' => true,
        'delivery_day_ids' => [$lunes->id, $viernes->id],
        'supplies' => [
            [
                'supply_id' => $this->supply->id,
                'agreed_price' => 29.00,
            ],
        ],
    ];

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/suppliers', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.company_name', 'Granja San Rafael')
        ->assertJsonPath('data.contact_name', 'Marta Soto')
        ->assertJsonPath('data.email', 'contacto@sanrafael.com');

    $this->assertDatabaseHas('suppliers', [
        'company_name' => 'Granja San Rafael',
        'is_active' => 1,
    ]);

    $newSupplier = Supplier::where('company_name', 'Granja San Rafael')->first();
    $this->assertCount(2, $newSupplier->deliveryDays);
    $this->assertCount(1, $newSupplier->supplierSupplies);
    $this->assertEquals(29.00, (float) $newSupplier->supplierSupplies->first()->agreed_price);
});

test('Scenario: Datos de proveedor incorrectos o incompletos no guardan el registro y solicitan correccion', function () {
    $payloadInvalid = [
        'company_name' => '', // Obligatorio vacio
        'email' => 'correo-no-valido',
        'supplies' => [
            [
                'supply_id' => 999999, // Insumo no existente
                'agreed_price' => -10, // Precio negativo invalido
            ],
        ],
    ];

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/suppliers', $payloadInvalid);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_name', 'email', 'delivery_day_ids', 'supplies.0.supply_id', 'supplies.0.agreed_price']);

    $this->assertDatabaseMissing('suppliers', [
        'email' => 'correo-no-valido',
    ]);
});

test('Crear o actualizar un proveedor requiere al menos un día de entrega seleccionado', function () {
    // Intento de creación con arreglo de días vacío
    $responseCreate = $this->actingAs($this->adminUser)->postJson('/api/suppliers', [
        'company_name' => 'Proveedor Sin Días',
        'delivery_day_ids' => [],
    ]);

    $responseCreate->assertStatus(422)
        ->assertJsonValidationErrors(['delivery_day_ids']);
    expect($responseCreate->json('errors.delivery_day_ids.0'))->toContain('Debe seleccionar al menos un día de entrega.');

    // Crear proveedor válido
    $lunes = DeliveryDay::where('name', 'Lunes')->first();
    $supplier = Supplier::create([
        'company_name' => 'Proveedor Con Días',
        'is_active' => true,
    ]);
    $supplier->deliveryDays()->sync([$lunes->id]);

    // Intento de actualización enviando arreglo vacío de días
    $responseUpdate = $this->actingAs($this->adminUser)->putJson("/api/suppliers/{$supplier->id}", [
        'company_name' => 'Proveedor Con Días Modificado',
        'delivery_day_ids' => [],
    ]);

    $responseUpdate->assertStatus(422)
        ->assertJsonValidationErrors(['delivery_day_ids']);
    expect($responseUpdate->json('errors.delivery_day_ids.0'))->toContain('Debe seleccionar al menos un día de entrega.');
});

test('Scenario: Desactivar un proveedor realiza baja logica y conserva su historial de compras', function () {
    $supplier = Supplier::create([
        'company_name' => 'Avícola La Esperanza',
        'contact_name' => 'Roberto Gómez',
        'is_active' => true,
    ]);

    $poStatus = PurchaseOrderStatus::where('name', 'recibida_completa')->first();
    $po = PurchaseOrder::create([
        'code' => 'OC-HISTORIAL-01',
        'supplier_id' => $supplier->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $poStatus->id,
        'total' => 800.00,
        'expected_date' => now()->toDateString(),
        'received_date' => now()->toDateString(),
    ]);

    $responseToggle = $this->actingAs($this->adminUser)
        ->patchJson("/api/suppliers/{$supplier->id}/status");

    $responseToggle->assertOk()
        ->assertJsonPath('data.is_active', false);

    $supplier->refresh();
    $this->assertFalse($supplier->is_active);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'supplier_id' => $supplier->id,
        'code' => 'OC-HISTORIAL-01',
    ]);

    $responseDetail = $this->actingAs($this->adminUser)
        ->getJson("/api/suppliers/{$supplier->id}");

    $responseDetail->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonFragment(['code' => 'OC-HISTORIAL-01']);
});

test('Scenario: Registrar una incidencia al recibir una entrega actualiza el historial del proveedor con la incidencia', function () {
    $supplier = Supplier::create([
        'company_name' => 'Distribuidora Pollo Rey',
        'is_active' => true,
    ]);

    $incidentType = DeliveryIncidentType::where('name', 'producto_danado')->first();

    $payload = [
        'has_incident' => true,
        'delivery_incident_type_id' => $incidentType->id,
        'description' => 'Tres cajas de pechuga llegaron con el empaque roto y mal refrigerado.',
        'items' => [
            [
                'supply_id' => $this->supply->id,
                'received_quantity' => 10,
                'unit_price' => 30.00,
            ],
        ],
    ];

    $response = $this->actingAs($this->waiterUser)
        ->postJson("/api/suppliers/{$supplier->id}/deliveries", $payload);

    $response->assertCreated()
        ->assertJsonPath('purchase_order.status', 'recibida_con_incidencia')
        ->assertJsonPath('incident.type', 'producto_danado')
        ->assertJsonPath('incident.description', 'Tres cajas de pechuga llegaron con el empaque roto y mal refrigerado.')
        ->assertJsonPath('incident.receiving_user_name', $this->waiterUser->name);

    $this->assertDatabaseHas('delivery_incidents', [
        'supplier_id' => $supplier->id,
        'delivery_incident_type_id' => $incidentType->id,
        'receiving_user_id' => $this->waiterUser->id,
    ]);

    $responseHistory = $this->actingAs($this->adminUser)
        ->getJson("/api/suppliers/{$supplier->id}/history");

    $responseHistory->assertOk()
        ->assertJsonCount(1, 'purchase_orders')
        ->assertJsonCount(1, 'delivery_incidents');
});

test('Scenario: Entrega sin incidencias actualiza el historial del proveedor sin registrar incidencia', function () {
    $supplier = Supplier::create([
        'company_name' => 'Avícola San José',
        'is_active' => true,
    ]);

    $payload = [
        'has_incident' => false,
        'items' => [
            [
                'supply_id' => $this->supply->id,
                'received_quantity' => 25,
                'unit_price' => 28.00,
            ],
        ],
    ];

    $response = $this->actingAs($this->waiterUser)
        ->postJson("/api/suppliers/{$supplier->id}/deliveries", $payload);

    $response->assertOk()
        ->assertJsonPath('purchase_order.status', 'recibida_completa')
        ->assertJsonPath('incident', null);

    $this->assertDatabaseMissing('delivery_incidents', [
        'supplier_id' => $supplier->id,
    ]);

    $responseHistory = $this->actingAs($this->adminUser)
        ->getJson("/api/suppliers/{$supplier->id}/history");

    $responseHistory->assertOk()
        ->assertJsonCount(1, 'purchase_orders')
        ->assertJsonCount(0, 'delivery_incidents');
});

test('No se permite modificar ni recibir entregas de un proveedor inactivo', function () {
    $inactiveSupplier = Supplier::create([
        'company_name' => 'Proveedor Inactivo S.A.',
        'is_active' => false,
    ]);

    // Intento de edición
    $responseEdit = $this->actingAs($this->adminUser)
        ->putJson("/api/suppliers/{$inactiveSupplier->id}", [
            'company_name' => 'Nombre Modificado',
        ]);

    $responseEdit->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'No se puede modificar la información de un proveedor inactivo. Debe activarlo primero.',
        ]);

    // Intento de recibir entrega
    $responseDelivery = $this->actingAs($this->waiterUser)
        ->postJson("/api/suppliers/{$inactiveSupplier->id}/deliveries", [
            'has_incident' => false,
        ]);

    $responseDelivery->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'No se pueden registrar entregas para un proveedor inactivo. Debe activarlo primero.',
        ]);
});

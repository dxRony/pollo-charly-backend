<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\DeliveryIncident;
use App\Models\DeliveryIncidentStatus;
use App\Models\DeliveryIncidentType;
use App\Models\MeasurementUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
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
    $this->cookRole = Role::where('name', 'Cocinero')->first();

    $this->adminUser = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'name' => 'Administradora Bertha',
        'email' => 'admin_supplier_rep@pollocharly.com',
    ]);

    $this->waiterUser = User::factory()->create([
        'role_id' => $this->waiterRole->id,
        'name' => 'Mesero Carlos',
        'email' => 'mesero_supplier_rep@pollocharly.com',
    ]);

    $this->cookUser = User::factory()->create([
        'role_id' => $this->cookRole->id,
        'name' => 'Cocinero Mario',
        'email' => 'cocinero_supplier_rep@pollocharly.com',
    ]);

    $this->supplierA = Supplier::create([
        'company_name' => 'Distribuidora Avícola San Jorge',
        'contact_name' => 'Jorge Pérez',
        'phone' => '55551111',
        'email' => 'sanjorge@test.com',
        'address' => 'Zona 1, Ciudad',
        'is_active' => true,
    ]);

    $this->supplierB = Supplier::create([
        'company_name' => 'Verduras del Valle',
        'contact_name' => 'Valeria Gómez',
        'phone' => '55552222',
        'email' => 'valle@test.com',
        'address' => 'Zona 4, Ciudad',
        'is_active' => true,
    ]);

    $this->unitKg = MeasurementUnit::where('abbreviation', 'kg')->first();

    $this->supplyChicken = Supply::create([
        'code' => 'INS-POLLO-TEST',
        'name' => 'Pollo Entero',
        'measurement_unit_id' => $this->unitKg->id,
        'minimum_stock' => 10,
        'current_stock' => 50,
        'unit_cost' => 20.00,
        'is_active' => true,
    ]);

    $this->statusCompleted = PurchaseOrderStatus::where('name', PurchaseOrderStatus::RECIBIDA_COMPLETA)->first();
    $this->statusIncident = PurchaseOrderStatus::where('name', PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA)->first();
    $this->statusRequested = PurchaseOrderStatus::where('name', PurchaseOrderStatus::SOLICITADA)->first();
    $this->statusCancelled = PurchaseOrderStatus::where('name', PurchaseOrderStatus::CANCELADA)->first();

    $this->incidentType = DeliveryIncidentType::where('name', 'peso_incompleto')->first();
    $this->incidentStatus = DeliveryIncidentStatus::where('name', 'reportada')->first();
});

test('el administrador puede consultar el reporte de compras y proveedores con kpis y agrupacion', function () {
    // Orden 1: Proveedor A, completa, Q500, a tiempo
    $order1 = PurchaseOrder::create([
        'code' => 'OC-2026-0001',
        'supplier_id' => $this->supplierA->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusCompleted->id,
        'total' => 500.00,
        'expected_date' => now()->subDays(2)->format('Y-m-d'),
        'received_date' => now()->subDays(2)->format('Y-m-d'),
    ]);
    PurchaseOrderItem::create([
        'purchase_order_id' => $order1->id,
        'supply_id' => $this->supplyChicken->id,
        'ordered_quantity' => 25.0,
        'received_quantity' => 25.0,
        'unit_price' => 20.00,
        'subtotal' => 500.00,
    ]);

    // Orden 2: Proveedor A, con incidencia, Q300, a tiempo
    $order2 = PurchaseOrder::create([
        'code' => 'OC-2026-0002',
        'supplier_id' => $this->supplierA->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusIncident->id,
        'total' => 300.00,
        'expected_date' => now()->subDays(1)->format('Y-m-d'),
        'received_date' => now()->subDays(1)->format('Y-m-d'),
    ]);
    DeliveryIncident::create([
        'purchase_order_id' => $order2->id,
        'supplier_id' => $this->supplierA->id,
        'receiving_user_id' => $this->adminUser->id,
        'delivery_incident_type_id' => $this->incidentType->id,
        'delivery_incident_status_id' => $this->incidentStatus->id,
        'description' => 'Faltaron 2 kilos en la entrega',
    ]);

    // Orden 3: Proveedor B, completa, Q200, tarde
    $order3 = PurchaseOrder::create([
        'code' => 'OC-2026-0003',
        'supplier_id' => $this->supplierB->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusCompleted->id,
        'total' => 200.00,
        'expected_date' => now()->subDays(3)->format('Y-m-d'),
        'received_date' => now()->subDays(1)->format('Y-m-d'),
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/reports/supplier-purchases');

    $response->assertOk()
        ->assertJsonStructure([
            'filters',
            'summary' => [
                'total_purchases_amount',
                'orders_count',
                'completed_orders_count',
                'incident_orders_count',
                'total_incidents',
                'fulfillment_rate',
                'top_supplier',
            ],
            'by_supplier',
            'rows',
        ]);

    $summary = $response->json('summary');
    expect($summary['orders_count'])->toBe(3)
        ->and((float) $summary['total_purchases_amount'])->toBe(1000.0)
        ->and($summary['completed_orders_count'])->toBe(2)
        ->and($summary['incident_orders_count'])->toBe(1)
        ->and($summary['total_incidents'])->toBe(1)
        ->and((float) $summary['fulfillment_rate'])->toBe(66.7)
        ->and($summary['top_supplier'])->toBe('Distribuidora Avícola San Jorge');

    $bySupplier = collect($response->json('by_supplier'));
    expect($bySupplier)->toHaveCount(2);

    $supplierAGroup = $bySupplier->firstWhere('supplier_name', 'Distribuidora Avícola San Jorge');
    expect((float) $supplierAGroup['total_spent'])->toBe(800.0)
        ->and($supplierAGroup['orders_count'])->toBe(2)
        ->and($supplierAGroup['completed_count'])->toBe(1)
        ->and($supplierAGroup['incident_count'])->toBe(1)
        ->and((float) $supplierAGroup['fulfillment_rate'])->toBe(50.0)
        ->and((float) $supplierAGroup['punctuality_rate'])->toBe(100.0);

    $supplierBGroup = $bySupplier->firstWhere('supplier_name', 'Verduras del Valle');
    expect((float) $supplierBGroup['total_spent'])->toBe(200.0)
        ->and((float) $supplierBGroup['punctuality_rate'])->toBe(0.0);
});

test('calcula correctamente puntualidad e incidencias en filas individuales', function () {
    // Orden a tiempo
    $order1 = PurchaseOrder::create([
        'code' => 'OC-TIEMPO-1',
        'supplier_id' => $this->supplierA->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusCompleted->id,
        'total' => 150.00,
        'expected_date' => '2026-10-05',
        'received_date' => '2026-10-05',
    ]);

    // Orden tarde con incidencia
    $order2 = PurchaseOrder::create([
        'code' => 'OC-TARDE-1',
        'supplier_id' => $this->supplierA->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusIncident->id,
        'total' => 250.00,
        'expected_date' => '2026-10-04',
        'received_date' => '2026-10-06',
    ]);
    DeliveryIncident::create([
        'purchase_order_id' => $order2->id,
        'supplier_id' => $this->supplierA->id,
        'receiving_user_id' => $this->adminUser->id,
        'delivery_incident_type_id' => $this->incidentType->id,
        'delivery_incident_status_id' => $this->incidentStatus->id,
        'description' => 'Faltaron piezas',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/reports/supplier-purchases');

    $response->assertOk();
    $rows = collect($response->json('rows'));

    $row1 = $rows->firstWhere('code', 'OC-TIEMPO-1');
    expect($row1['punctuality'])->toBe('A tiempo')
        ->and($row1['incidents_summary'])->toBe('Ninguna')
        ->and($row1['incidents_count'])->toBe(0);

    $row2 = $rows->firstWhere('code', 'OC-TARDE-1');
    expect($row2['punctuality'])->toBe('Tarde')
        ->and($row2['incidents_count'])->toBe(1)
        ->and($row2['incidents_summary'])->toContain('Peso incompleto');
});

test('filtra correctamente por proveedor, estado y fechas', function () {
    $orderA = PurchaseOrder::create([
        'code' => 'OC-FILTRO-A',
        'supplier_id' => $this->supplierA->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusCompleted->id,
        'total' => 100.00,
        'expected_date' => now()->format('Y-m-d'),
        'received_date' => now()->format('Y-m-d'),
    ]);
    $orderA->created_at = now()->subDays(10);
    $orderA->save();

    $orderB = PurchaseOrder::create([
        'code' => 'OC-FILTRO-B',
        'supplier_id' => $this->supplierB->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusRequested->id,
        'total' => 200.00,
        'expected_date' => now()->format('Y-m-d'),
    ]);
    $orderB->created_at = now();
    $orderB->save();

    // Filtro por supplier_id
    $resSupp = $this->actingAs($this->adminUser)
        ->getJson("/api/reports/supplier-purchases?supplier_id={$this->supplierA->id}");
    $resSupp->assertOk();
    expect($resSupp->json('rows'))->toHaveCount(1)
        ->and($resSupp->json('rows.0.code'))->toBe('OC-FILTRO-A');

    // Filtro por status
    $resStatus = $this->actingAs($this->adminUser)
        ->getJson('/api/reports/supplier-purchases?status=solicitada');
    $resStatus->assertOk();
    expect($resStatus->json('rows'))->toHaveCount(1)
        ->and($resStatus->json('rows.0.code'))->toBe('OC-FILTRO-B');

    // Filtro por fecha (hoy)
    $today = now()->format('Y-m-d');
    $resDate = $this->actingAs($this->adminUser)
        ->getJson("/api/reports/supplier-purchases?date_from={$today}&date_to={$today}");
    $resDate->assertOk();
    expect($resDate->json('rows'))->toHaveCount(1)
        ->and($resDate->json('rows.0.code'))->toBe('OC-FILTRO-B');
});

test('permite exportar el reporte a pdf y excel', function () {
    PurchaseOrder::create([
        'code' => 'OC-EXP-1',
        'supplier_id' => $this->supplierA->id,
        'admin_user_id' => $this->adminUser->id,
        'purchase_order_status_id' => $this->statusCompleted->id,
        'total' => 100.00,
        'expected_date' => now()->format('Y-m-d'),
        'received_date' => now()->format('Y-m-d'),
    ]);

    // Exportación a Excel
    $excelResponse = $this->actingAs($this->adminUser)
        ->get('/api/reports/supplier-purchases?format=xlsx');
    $excelResponse->assertOk()
        ->assertHeader('content-disposition');

    // Exportación a PDF
    $pdfResponse = $this->actingAs($this->adminUser)
        ->get('/api/reports/supplier-purchases?format=pdf');
    $pdfResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('usuarios sin rol administrador no pueden acceder al reporte de compras', function () {
    $this->actingAs($this->waiterUser)
        ->getJson('/api/reports/supplier-purchases')
        ->assertForbidden();

    $this->actingAs($this->cookUser)
        ->getJson('/api/reports/supplier-purchases')
        ->assertForbidden();

    $this->getJson('/api/reports/supplier-purchases')
        ->assertForbidden();
});

<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\AdjustmentStatusType;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\MeasurementUnit;
use App\Models\Role;
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
        'email' => 'admin_waste@pollocharly.com',
    ]);

    $this->waiterUser = User::factory()->create([
        'role_id' => $this->waiterRole->id,
        'name' => 'Mesero Carlos',
        'email' => 'mesero_waste@pollocharly.com',
    ]);

    $this->cookUser = User::factory()->create([
        'role_id' => $this->cookRole->id,
        'name' => 'Cocinero Mario',
        'email' => 'cocinero_waste@pollocharly.com',
    ]);

    $this->unitKg = MeasurementUnit::where('abbreviation', 'kg')->first();
    $this->unitUnid = MeasurementUnit::where('abbreviation', 'u')->first();

    $this->supplyChicken = Supply::create([
        'code' => 'INS-POLLO-1',
        'name' => 'Pechuga de Pollo',
        'measurement_unit_id' => $this->unitKg->id,
        'minimum_stock' => 10,
        'current_stock' => 50,
        'unit_cost' => 25.00,
        'is_active' => true,
    ]);

    $this->supplyOil = Supply::create([
        'code' => 'INS-ACEITE-1',
        'name' => 'Aceite Vegetal',
        'measurement_unit_id' => $this->unitUnid->id,
        'minimum_stock' => 5,
        'current_stock' => 20,
        'unit_cost' => 40.00,
        'is_active' => true,
    ]);

    $this->mermaType = InventoryMovementType::where('name', InventoryMovementType::MERMA_DANO)->first();
    $this->ajusteType = InventoryMovementType::where('name', InventoryMovementType::AJUSTE_INVENTARIO)->first();

    $this->approvedStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::APROBADO)->first();
    $this->pendingStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::PENDIENTE_APROBACION)->first();
    $this->rejectedStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::RECHAZADO)->first();
});

test('el administrador puede consultar el reporte de mermas en formato json con kpis y agrupacion', function () {
    // Merma 1: 2 kg de pollo por daño (2 * 25.00 = 50.00)
    InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->mermaType->id,
        'user_id' => $this->cookUser->id,
        'quantity' => 2.0,
        'previous_stock' => 50.0,
        'new_stock' => 48.0,
        'reason' => 'Pechuga vencida',
    ]);

    // Merma 2: 1 aceite por frasco quebrado (1 * 40.00 = 40.00)
    InventoryMovement::create([
        'supply_id' => $this->supplyOil->id,
        'inventory_movement_type_id' => $this->mermaType->id,
        'user_id' => $this->waiterUser->id,
        'quantity' => 1.0,
        'previous_stock' => 20.0,
        'new_stock' => 19.0,
        'reason' => 'Botella quebrada al descargar',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/reports/inventory-waste');

    $response->assertOk()
        ->assertJsonStructure([
            'filters',
            'summary' => [
                'total_loss_cost',
                'total_quantity',
                'records_count',
                'top_wasted_supply',
            ],
            'by_supply',
            'rows',
        ]);

    $summary = $response->json('summary');
    expect($summary['records_count'])->toBe(2)
        ->and((float) $summary['total_quantity'])->toBe(3.0)
        ->and((float) $summary['total_loss_cost'])->toBe(90.0)
        ->and($summary['top_wasted_supply'])->toBe('Pechuga de Pollo');

    $bySupply = collect($response->json('by_supply'));
    expect($bySupply)->toHaveCount(2);

    $chickenGroup = $bySupply->firstWhere('supply_name', 'Pechuga de Pollo');
    expect((float) $chickenGroup['total_cost'])->toBe(50.0)
        ->and((float) $chickenGroup['total_quantity'])->toBe(2.0);
});

test('incluye ajustes negativos aprobados y excluye ajustes pendientes o rechazados', function () {
    // Ajuste aprobado con reducción: stock bajó de 50 a 46 (pérdida de 4 kg = 4 * 25.00 = 100.00)
    InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->ajusteType->id,
        'user_id' => $this->cookUser->id,
        'approver_user_id' => $this->adminUser->id,
        'adjustment_status_type_id' => $this->approvedStatus->id,
        'quantity' => 4.0,
        'previous_stock' => 50.0,
        'new_stock' => 46.0,
        'reason' => 'Faltante confirmado en conteo',
    ]);

    // Ajuste pendiente: NO debe incluirse
    InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->ajusteType->id,
        'user_id' => $this->cookUser->id,
        'adjustment_status_type_id' => $this->pendingStatus->id,
        'quantity' => 5.0,
        'previous_stock' => 46.0,
        'new_stock' => 41.0,
        'reason' => 'Solicitud pendiente',
    ]);

    // Ajuste rechazado: NO debe incluirse
    InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->ajusteType->id,
        'user_id' => $this->cookUser->id,
        'approver_user_id' => $this->adminUser->id,
        'adjustment_status_type_id' => $this->rejectedStatus->id,
        'quantity' => 2.0,
        'previous_stock' => 46.0,
        'new_stock' => 44.0,
        'reason' => 'Rechazado por error',
    ]);

    // Ajuste positivo (sobrante, stock aumentó de 46 a 50): NO es merma/pérdida
    InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->ajusteType->id,
        'user_id' => $this->cookUser->id,
        'approver_user_id' => $this->adminUser->id,
        'adjustment_status_type_id' => $this->approvedStatus->id,
        'quantity' => 4.0,
        'previous_stock' => 46.0,
        'new_stock' => 50.0,
        'reason' => 'Sobrante encontrado',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/reports/inventory-waste');

    $response->assertOk();
    $rows = $response->json('rows');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['reason'])->toBe('Faltante confirmado en conteo')
        ->and((float) $rows[0]['quantity'])->toBe(4.0)
        ->and((float) $rows[0]['total_cost'])->toBe(100.0)
        ->and($rows[0]['approver_name'])->toBe('Administradora Bertha');
});

test('filtra correctamente por insumo, usuario y fechas', function () {
    // Movimiento 1: Pollo por cookUser hace 5 días
    $m1 = InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->mermaType->id,
        'user_id' => $this->cookUser->id,
        'quantity' => 2.0,
        'previous_stock' => 50.0,
        'new_stock' => 48.0,
        'reason' => 'Merma pollo cook',
    ]);
    $m1->created_at = now()->subDays(5);
    $m1->save();

    // Movimiento 2: Aceite por waiterUser hoy
    $m2 = InventoryMovement::create([
        'supply_id' => $this->supplyOil->id,
        'inventory_movement_type_id' => $this->mermaType->id,
        'user_id' => $this->waiterUser->id,
        'quantity' => 1.0,
        'previous_stock' => 20.0,
        'new_stock' => 19.0,
        'reason' => 'Merma aceite waiter',
    ]);
    $m2->created_at = now();
    $m2->save();

    // Filtrar por supply_id del pollo
    $resSupply = $this->actingAs($this->adminUser)
        ->getJson("/api/reports/inventory-waste?supply_id={$this->supplyChicken->id}");
    $resSupply->assertOk();
    expect($resSupply->json('rows'))->toHaveCount(1)
        ->and($resSupply->json('rows.0.supply_name'))->toBe('Pechuga de Pollo');

    // Filtrar por user_id del mesero
    $resUser = $this->actingAs($this->adminUser)
        ->getJson("/api/reports/inventory-waste?user_id={$this->waiterUser->id}");
    $resUser->assertOk();
    expect($resUser->json('rows'))->toHaveCount(1)
        ->and($resUser->json('rows.0.supply_name'))->toBe('Aceite Vegetal');

    // Filtrar por fechas recientes (solo hoy)
    $today = now()->format('Y-m-d');
    $resDate = $this->actingAs($this->adminUser)
        ->getJson("/api/reports/inventory-waste?date_from={$today}&date_to={$today}");
    $resDate->assertOk();
    expect($resDate->json('rows'))->toHaveCount(1)
        ->and($resDate->json('rows.0.supply_name'))->toBe('Aceite Vegetal');
});

test('permite exportar el reporte a pdf y excel', function () {
    InventoryMovement::create([
        'supply_id' => $this->supplyChicken->id,
        'inventory_movement_type_id' => $this->mermaType->id,
        'user_id' => $this->cookUser->id,
        'quantity' => 2.0,
        'previous_stock' => 50.0,
        'new_stock' => 48.0,
        'reason' => 'Merma de prueba',
    ]);

    // Exportación a Excel
    $excelResponse = $this->actingAs($this->adminUser)
        ->get('/api/reports/inventory-waste?format=xlsx');
    $excelResponse->assertOk()
        ->assertHeader('content-disposition');

    // Exportación a PDF
    $pdfResponse = $this->actingAs($this->adminUser)
        ->get('/api/reports/inventory-waste?format=pdf');
    $pdfResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('usuarios sin rol administrador no pueden acceder al reporte de mermas', function () {
    $this->actingAs($this->waiterUser)
        ->getJson('/api/reports/inventory-waste')
        ->assertForbidden();

    $this->actingAs($this->cookUser)
        ->getJson('/api/reports/inventory-waste')
        ->assertForbidden();

    $this->getJson('/api/reports/inventory-waste')
        ->assertForbidden();
});

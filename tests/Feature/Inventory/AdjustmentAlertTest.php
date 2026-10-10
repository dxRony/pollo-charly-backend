<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\AdjustmentStatusType;
use App\Models\AlertOrigin;
use App\Models\AlertStatus;
use App\Models\InventoryMovement;
use App\Models\MeasurementUnit;
use App\Models\Role;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\User;
use App\Notifications\SupplyAlertNotification;
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
        'name' => 'Administradora Bertha',
        'email' => 'admin_test@pollocharly.com',
    ]);

    $this->waiterUser = User::factory()->create([
        'role_id' => $this->waiterRole->id,
        'name' => 'Mesero Carlos',
        'email' => 'mesero_test@pollocharly.com',
    ]);

    $this->cookUser = User::factory()->create([
        'role_id' => $this->cookRole->id,
        'name' => 'Cocinero Mario',
        'email' => 'cocinero_test@pollocharly.com',
    ]);

    $this->unit = MeasurementUnit::where('abbreviation', 'kg')->first();

    $this->supply = Supply::create([
        'code' => 'INS-POLLO-1',
        'name' => 'Pechuga de Pollo Fresca',
        'measurement_unit_id' => $this->unit->id,
        'minimum_stock' => 10,
        'current_stock' => 20,
        'unit_cost' => 30.00,
        'is_active' => true,
    ]);
});

test('mesero/cajero can register manual inventory adjustment and creates alert for admin', function () {
    Notification::fake();

    $response = $this->actingAs($this->waiterUser)->postJson('/api/inventory-movements', [
        'supply_id' => $this->supply->id,
        'type' => 'ajuste',
        'new_stock' => 15,
        'reason' => 'Diferencia encontrada en conteo matutino de congelador',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('movement.new_stock', 15)
        ->assertJsonPath('movement.previous_stock', 20)
        ->assertJsonPath('movement.adjustment_status.name', 'pendiente_aprobacion');

    // Physical stock remains unchanged until approval
    $this->supply->refresh();
    expect((float) $this->supply->current_stock)->toBe(20.0);

    // Verify SupplyAlert created
    $movementId = $response->json('movement.id');
    $alert = SupplyAlert::where('inventory_movement_id', $movementId)->first();

    expect($alert)->not->toBeNull();
    expect($alert->supply_id)->toBe($this->supply->id);
    expect($alert->user_id)->toBe($this->waiterUser->id);
    expect($alert->status->name)->toBe(AlertStatus::PENDING);
    expect($alert->notes)->toBe('Diferencia encontrada en conteo matutino de congelador');

    // Verify admin was notified
    Notification::assertSentTo($this->adminUser, SupplyAlertNotification::class, function ($notification) use ($alert) {
        return $notification->alert->id === $alert->id;
    });
});

test('cocinero can register manual inventory adjustment and creates alert for admin', function () {
    Notification::fake();

    $response = $this->actingAs($this->cookUser)->postJson('/api/inventory-movements', [
        'supply_id' => $this->supply->id,
        'type' => 'ajuste_inventario',
        'new_stock' => 18,
        'reason' => 'Ajuste por merma no registrada durante preparación',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('movement.new_stock', 18)
        ->assertJsonPath('movement.adjustment_status.name', 'pendiente_aprobacion');

    $movementId = $response->json('movement.id');
    $alert = SupplyAlert::where('inventory_movement_id', $movementId)->first();

    expect($alert)->not->toBeNull();
    expect($alert->user_id)->toBe($this->cookUser->id);
    expect($alert->notes)->toBe('Ajuste por merma no registrada durante preparación');

    Notification::assertSentTo($this->adminUser, SupplyAlertNotification::class);
});

test('administradora can approve pending inventory adjustment', function () {
    $pendingStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::PENDIENTE_APROBACION)->first();
    $manualOrigin = AlertOrigin::where('name', AlertOrigin::MANUAL)->first();
    $pendingAlertStatus = AlertStatus::where('name', AlertStatus::PENDING)->first();

    $movement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 4, // Ajuste
        'user_id' => $this->waiterUser->id,
        'quantity' => 5,
        'previous_stock' => 20,
        'new_stock' => 15,
        'reason' => 'Conteo físico',
        'adjustment_status_type_id' => $pendingStatus->id,
    ]);

    $alert = SupplyAlert::create([
        'supply_id' => $this->supply->id,
        'alert_origin_id' => $manualOrigin->id,
        'alert_status_id' => $pendingAlertStatus->id,
        'user_id' => $this->waiterUser->id,
        'inventory_movement_id' => $movement->id,
        'notes' => 'Ajuste solicitado por mesero',
    ]);

    $response = $this->actingAs($this->adminUser)->postJson("/api/inventory-movements/{$movement->id}/approve", [
        'reason' => 'Aprobado conforme con conteo de almacén',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('movement.adjustment_status.name', 'aprobado');

    // Stock updated to new_stock
    $this->supply->refresh();
    expect((float) $this->supply->current_stock)->toBe(15.0);

    // Linked alert marked as attended
    $alert->refresh();
    expect($alert->status->name)->toBe(AlertStatus::ATTENDED);
});

test('administradora can reject pending inventory adjustment', function () {
    $pendingStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::PENDIENTE_APROBACION)->first();
    $manualOrigin = AlertOrigin::where('name', AlertOrigin::MANUAL)->first();
    $pendingAlertStatus = AlertStatus::where('name', AlertStatus::PENDING)->first();

    $movement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 4, // Ajuste
        'user_id' => $this->cookUser->id,
        'quantity' => 10,
        'previous_stock' => 20,
        'new_stock' => 10,
        'reason' => 'Diferencia no justificada',
        'adjustment_status_type_id' => $pendingStatus->id,
    ]);

    $alert = SupplyAlert::create([
        'supply_id' => $this->supply->id,
        'alert_origin_id' => $manualOrigin->id,
        'alert_status_id' => $pendingAlertStatus->id,
        'user_id' => $this->cookUser->id,
        'inventory_movement_id' => $movement->id,
        'notes' => 'Ajuste por cocinero',
    ]);

    $response = $this->actingAs($this->adminUser)->postJson("/api/inventory-movements/{$movement->id}/reject", [
        'reason' => 'Rechazado, se realizará un nuevo conteo con supervisión',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('movement.adjustment_status.name', 'rechazado');

    // Stock remains unchanged
    $this->supply->refresh();
    expect((float) $this->supply->current_stock)->toBe(20.0);

    // Alert marked as attended
    $alert->refresh();
    expect($alert->status->name)->toBe(AlertStatus::ATTENDED);
});

test('non-admin users cannot approve or reject inventory adjustments', function () {
    $pendingStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::PENDIENTE_APROBACION)->first();

    $movement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 4,
        'user_id' => $this->waiterUser->id,
        'quantity' => 5,
        'previous_stock' => 20,
        'new_stock' => 15,
        'reason' => 'Prueba',
        'adjustment_status_type_id' => $pendingStatus->id,
    ]);

    $this->actingAs($this->waiterUser)
        ->postJson("/api/inventory-movements/{$movement->id}/approve")
        ->assertStatus(403);

    $this->actingAs($this->cookUser)
        ->postJson("/api/inventory-movements/{$movement->id}/reject")
        ->assertStatus(403);
});

test('supply alert list returns inventory movement details', function () {
    $pendingStatus = AdjustmentStatusType::where('name', AdjustmentStatusType::PENDIENTE_APROBACION)->first();
    $manualOrigin = AlertOrigin::where('name', AlertOrigin::MANUAL)->first();
    $pendingAlertStatus = AlertStatus::where('name', AlertStatus::PENDING)->first();

    $movement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 4,
        'user_id' => $this->waiterUser->id,
        'quantity' => 5,
        'previous_stock' => 20,
        'new_stock' => 15,
        'reason' => 'Motivo de prueba',
        'adjustment_status_type_id' => $pendingStatus->id,
    ]);

    SupplyAlert::create([
        'supply_id' => $this->supply->id,
        'alert_origin_id' => $manualOrigin->id,
        'alert_status_id' => $pendingAlertStatus->id,
        'user_id' => $this->waiterUser->id,
        'inventory_movement_id' => $movement->id,
        'notes' => 'Alerta con movimiento vinculado',
    ]);

    $response = $this->actingAs($this->adminUser)->getJson('/api/supply-alerts');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.inventory_movement_id', $movement->id)
        ->assertJsonPath('data.0.inventory_movement.reason', 'Motivo de prueba')
        ->assertJsonPath('data.0.inventory_movement.new_stock', 15)
        ->assertJsonPath('data.0.inventory_movement.previous_stock', 20);
});

test('non-admin users only see their own inventory movements in list and cannot view others', function () {
    // Movement by Admin
    $adminMovement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 1,
        'user_id' => $this->adminUser->id,
        'quantity' => 20,
        'previous_stock' => 0,
        'new_stock' => 20,
        'reason' => 'Compra por admin',
    ]);

    // Movement by Waiter
    $waiterMovement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 4,
        'user_id' => $this->waiterUser->id,
        'quantity' => 5,
        'previous_stock' => 20,
        'new_stock' => 15,
        'reason' => 'Ajuste por mesero',
    ]);

    // Movement by Cook
    $cookMovement = InventoryMovement::create([
        'supply_id' => $this->supply->id,
        'inventory_movement_type_id' => 4,
        'user_id' => $this->cookUser->id,
        'quantity' => 2,
        'previous_stock' => 15,
        'new_stock' => 13,
        'reason' => 'Ajuste por cocinero',
    ]);

    // Admin sees all 3 movements
    $adminRes = $this->actingAs($this->adminUser)->getJson('/api/inventory-movements');
    $adminRes->assertStatus(200);
    expect($adminRes->json('total'))->toBe(3);

    // Waiter only sees 1 (his own)
    $waiterRes = $this->actingAs($this->waiterUser)->getJson('/api/inventory-movements');
    $waiterRes->assertStatus(200);
    expect($waiterRes->json('total'))->toBe(1);
    expect($waiterRes->json('data.0.id'))->toBe($waiterMovement->id);

    // Cook only sees 1 (his own)
    $cookRes = $this->actingAs($this->cookUser)->getJson('/api/inventory-movements');
    $cookRes->assertStatus(200);
    expect($cookRes->json('total'))->toBe(1);
    expect($cookRes->json('data.0.id'))->toBe($cookMovement->id);

    // Waiter cannot view cook's movement detail (403)
    $this->actingAs($this->waiterUser)
        ->getJson("/api/inventory-movements/{$cookMovement->id}")
        ->assertStatus(403);

    // Waiter can view his own movement detail (200)
    $this->actingAs($this->waiterUser)
        ->getJson("/api/inventory-movements/{$waiterMovement->id}")
        ->assertStatus(200);

    // Admin can view cook's movement detail (200)
    $this->actingAs($this->adminUser)
        ->getJson("/api/inventory-movements/{$cookMovement->id}")
        ->assertStatus(200);
});

test('cannot directly attend an inventory adjustment alert via supply-alerts attend endpoint', function () {
    $response = $this->actingAs($this->waiterUser)->postJson('/api/inventory-movements', [
        'supply_id' => $this->supply->id,
        'type' => 'ajuste',
        'new_stock' => 12,
        'reason' => 'Ajuste para test de atencion directa',
    ]);

    $response->assertStatus(201);
    $movementId = $response->json('movement.id');
    $alert = SupplyAlert::where('inventory_movement_id', $movementId)->firstOrFail();

    $attendResponse = $this->actingAs($this->adminUser)
        ->postJson("/api/supply-alerts/{$alert->id}/attend");

    $attendResponse->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Esta alerta corresponde a una solicitud de ajuste de inventario. Debe aprobarse o rechazarse desde el módulo de revisión de ajustes.',
        ]);

    $alert->refresh();
    expect($alert->status->name)->toBe(AlertStatus::PENDING);
});

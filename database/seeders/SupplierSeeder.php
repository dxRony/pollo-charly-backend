<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeliveryDay;
use App\Models\DeliveryIncident;
use App\Models\DeliveryIncidentStatus;
use App\Models\DeliveryIncidentType;
use App\Models\MeasurementUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();
        $kg = MeasurementUnit::where('abbreviation', 'kg')->first();

        // Insumos base si no existen
        $pechuga = Supply::firstOrCreate(
            ['code' => 'INS-POLLO-01'],
            [
                'name' => 'Pechuga de Pollo Fresca',
                'measurement_unit_id' => $kg?->id ?? 1,
                'minimum_stock' => 20,
                'unit_cost' => 32.00,
                'is_active' => true,
            ]
        );

        $alas = Supply::firstOrCreate(
            ['code' => 'INS-POLLO-02'],
            [
                'name' => 'Alas de Pollo',
                'measurement_unit_id' => $kg?->id ?? 1,
                'minimum_stock' => 15,
                'unit_cost' => 28.00,
                'is_active' => true,
            ]
        );

        // Proveedor: Distribuidora Avícola
        $avicola = Supplier::firstOrCreate(
            ['company_name' => 'Distribuidora Avícola'],
            [
                'contact_name' => 'Juan Pérez Gómez',
                'phone' => '555-4321',
                'email' => 'ventas@avicola.com',
                'address' => 'Calzada San Juan 14-22, Zona 7',
                'is_active' => true,
            ]
        );

        // Días de entrega: Lunes, Miércoles, Viernes
        $dias = DeliveryDay::whereIn('name', ['Lunes', 'Miércoles', 'Viernes'])->pluck('id');
        $avicola->deliveryDays()->sync($dias);

        // Productos provistos y precios acordados
        $avicola->supplierSupplies()->updateOrCreate(
            ['supply_id' => $pechuga->id],
            ['agreed_price' => 30.50]
        );
        $avicola->supplierSupplies()->updateOrCreate(
            ['supply_id' => $alas->id],
            ['agreed_price' => 26.00]
        );

        // Proveedor secundario de ejemplo
        $granja = Supplier::firstOrCreate(
            ['company_name' => 'Granja Santa Anita'],
            [
                'contact_name' => 'Ana Morales',
                'phone' => '555-8765',
                'email' => 'pedidos@granjasantaanita.com',
                'address' => 'Km 24 Carretera a El Salvador',
                'is_active' => true,
            ]
        );

        $martesJueves = DeliveryDay::whereIn('name', ['Martes', 'Jueves'])->pluck('id');
        $granja->deliveryDays()->sync($martesJueves);
        $granja->supplierSupplies()->updateOrCreate(
            ['supply_id' => $pechuga->id],
            ['agreed_price' => 31.00]
        );

        // Registro de una orden previa con incidencia para demostrar el historial
        $statusRecibida = PurchaseOrderStatus::where('name', 'recibida_con_incidencia')->first();
        if ($admin && $statusRecibida) {
            $po = PurchaseOrder::firstOrCreate(
                ['code' => 'OC-2026-0001'],
                [
                    'supplier_id' => $avicola->id,
                    'admin_user_id' => $admin->id,
                    'purchase_order_status_id' => $statusRecibida->id,
                    'total' => 610.00,
                    'expected_date' => now()->subDays(3)->toDateString(),
                    'received_date' => now()->subDays(3)->toDateString(),
                ]
            );

            $incidentType = DeliveryIncidentType::where('name', 'peso_incompleto')->first();
            $incidentStatus = DeliveryIncidentStatus::where('name', 'reportada')->first();

            if ($incidentType && $incidentStatus) {
                DeliveryIncident::firstOrCreate(
                    [
                        'purchase_order_id' => $po->id,
                        'supplier_id' => $avicola->id,
                    ],
                    [
                        'receiving_user_id' => $admin->id,
                        'delivery_incident_type_id' => $incidentType->id,
                        'delivery_incident_status_id' => $incidentStatus->id,
                        'description' => 'Faltaron 3 kg de pechuga respecto a la orden de compra.',
                    ]
                );
            }
        }
    }
}

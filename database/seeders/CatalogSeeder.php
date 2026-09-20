<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    /**
     * Seed all system lookup catalogs.
     */
    public function run(): void
    {
        $now = now();

        //Categories
        $categories = [
            ['name' => 'Pollos', 'description' => 'Piezas individuales, medios pollos y pollos enteros (frito o asado)', 'is_active' => true],
            ['name' => 'Combos', 'description' => 'Combos individuales y familiares con complementos y bebidas', 'is_active' => true],
            ['name' => 'Complementos', 'description' => 'Guarniciones y extras (papas, ensalada, tortillas, etc.)', 'is_active' => true],
            ['name' => 'Bebidas', 'description' => 'Gaseosas, aguas y bebidas naturales', 'is_active' => true],
            ['name' => 'Postres', 'description' => 'Postres y dulces del restaurante', 'is_active' => true],
            ['name' => 'Salsas y Extras', 'description' => 'Porciones adicionales de salsas y aderezos', 'is_active' => true],
        ];
        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(['name' => $cat['name']], array_merge($cat, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Measurement Units
        $measurementUnits = [
            ['name' => 'Kilogramo', 'abbreviation' => 'kg'],
            ['name' => 'Gramo', 'abbreviation' => 'g'],
            ['name' => 'Litro', 'abbreviation' => 'l'],
            ['name' => 'Mililitro', 'abbreviation' => 'ml'],
            ['name' => 'Unidad', 'abbreviation' => 'u'],
            ['name' => 'Porción', 'abbreviation' => 'porc'],
            ['name' => 'Paquete', 'abbreviation' => 'paq'],
        ];
        foreach ($measurementUnits as $unit) {
            DB::table('measurement_units')->updateOrInsert(['name' => $unit['name']], array_merge($unit, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Table Statuses
        $tableStatuses = [
            ['name' => 'disponible'],
            ['name' => 'ocupada'],
            ['name' => 'mantenimiento'],
            ['name' => 'inactiva'],
        ];
        foreach ($tableStatuses as $status) {
            DB::table('table_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Order Types
        $orderTypes = [
            ['name' => 'en_mesa'],
            ['name' => 'para_llevar'],
        ];
        foreach ($orderTypes as $type) {
            DB::table('order_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Order Statuses
        $orderStatuses = [
            ['name' => 'pendiente'],
            ['name' => 'en_preparacion'],
            ['name' => 'lista'],
            ['name' => 'entregada'],
            ['name' => 'cancelada'],
        ];
        foreach ($orderStatuses as $status) {
            DB::table('order_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Order Item Statuses
        $orderItemStatuses = [
            ['name' => 'pendiente'],
            ['name' => 'en_preparacion'],
            ['name' => 'listo'],
            ['name' => 'cancelado'],
            ['name' => 'eliminado'],
        ];
        foreach ($orderItemStatuses as $status) {
            DB::table('order_item_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Receipt Types
        $receiptTypes = [
            ['name' => 'ticket'],
            ['name' => 'factura'],
        ];
        foreach ($receiptTypes as $type) {
            DB::table('receipt_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Payment Methods
        $paymentMethods = [
            ['name' => 'efectivo'],
            ['name' => 'tarjeta'],
            ['name' => 'transferencia'],
            ['name' => 'mixto'],
        ];
        foreach ($paymentMethods as $method) {
            DB::table('payment_methods')->updateOrInsert(['name' => $method['name']], array_merge($method, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Sale Statuses
        $saleStatuses = [
            ['name' => 'completada'],
            ['name' => 'anulada'],
        ];
        foreach ($saleStatuses as $status) {
            DB::table('sale_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Cash Movement Types
        $cashMovementTypes = [
            ['name' => 'ingreso'],
            ['name' => 'egreso'],
            ['name' => 'gasto_operativo'],
        ];
        foreach ($cashMovementTypes as $type) {
            DB::table('cash_movement_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Cash Movement Categories
        $cashMovementCategories = [
            ['name' => 'venta'],
            ['name' => 'pago_proveedor'],
            ['name' => 'servicios'],
            ['name' => 'mantenimiento'],
            ['name' => 'sueldos'],
            ['name' => 'compras_menores'],
            ['name' => 'otro'],
        ];
        foreach ($cashMovementCategories as $cat) {
            DB::table('cash_movement_categories')->updateOrInsert(['name' => $cat['name']], array_merge($cat, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Delivery Days
        $deliveryDays = [
            ['name' => 'Lunes'],
            ['name' => 'Martes'],
            ['name' => 'Miércoles'],
            ['name' => 'Jueves'],
            ['name' => 'Viernes'],
            ['name' => 'Sábado'],
            ['name' => 'Domingo'],
        ];
        foreach ($deliveryDays as $day) {
            DB::table('delivery_days')->updateOrInsert(['name' => $day['name']], array_merge($day, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Purchase Request Statuses
        $purchaseRequestStatuses = [
            ['name' => 'pendiente'],
            ['name' => 'aprobada'],
            ['name' => 'rechazada_sin_comprar'],
            ['name' => 'procesada'],
        ];
        foreach ($purchaseRequestStatuses as $status) {
            DB::table('purchase_request_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Purchase Order Statuses
        $purchaseOrderStatuses = [
            ['name' => 'solicitada'],
            ['name' => 'recibida_completa'],
            ['name' => 'recibida_con_incidencia'],
            ['name' => 'cancelada'],
        ];
        foreach ($purchaseOrderStatuses as $status) {
            DB::table('purchase_order_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Delivery Incident Types
        $deliveryIncidentTypes = [
            ['name' => 'peso_incompleto'],
            ['name' => 'producto_danado'],
            ['name' => 'producto_equivocado'],
            ['name' => 'retraso'],
            ['name' => 'otro'],
        ];
        foreach ($deliveryIncidentTypes as $type) {
            DB::table('delivery_incident_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Delivery Incident Statuses
        $deliveryIncidentStatuses = [
            ['name' => 'reportada'],
            ['name' => 'en_correccion'],
            ['name' => 'resuelta'],
        ];
        foreach ($deliveryIncidentStatuses as $status) {
            DB::table('delivery_incident_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Inventory Movement Types
        $inventoryMovementTypes = [
            ['name' => 'compra_entrada'],
            ['name' => 'consumo_venta'],
            ['name' => 'merma_dano'],
            ['name' => 'ajuste_inventario'],
            ['name' => 'cancelacion_pedido'],
        ];
        foreach ($inventoryMovementTypes as $type) {
            DB::table('inventory_movement_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Adjustment Status Types
        $adjustmentStatusTypes = [
            ['name' => 'aprobado'],
            ['name' => 'pendiente_aprobacion'],
            ['name' => 'rechazado'],
        ];
        foreach ($adjustmentStatusTypes as $type) {
            DB::table('adjustment_status_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Alert Origins
        $alertOrigins = [
            ['name' => 'manual'],
            ['name' => 'automatic'],
        ];
        foreach ($alertOrigins as $origin) {
            DB::table('alert_origins')->updateOrInsert(['name' => $origin['name']], array_merge($origin, ['created_at' => $now, 'updated_at' => $now]));
        }

        //Alert Statuses
        $alertStatuses = [
            ['name' => 'pending'],
            ['name' => 'attended'],
        ];
        foreach ($alertStatuses as $status) {
            DB::table('alert_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }
    }
}

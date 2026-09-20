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

        // 1. Categories (Menu categories - HU-06, HU-08, BPMN 9)
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

        // 2. Measurement Units (HU-09)
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

        // 3. Table Statuses (HU-22: disponible, ocupada, mantenimiento, inactiva)
        $tableStatuses = [
            ['name' => 'disponible'],
            ['name' => 'ocupada'],
            ['name' => 'mantenimiento'],
            ['name' => 'inactiva'],
        ];
        foreach ($tableStatuses as $status) {
            DB::table('table_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 4. Order Types (HU-15)
        $orderTypes = [
            ['name' => 'en_mesa'],
            ['name' => 'para_llevar'],
        ];
        foreach ($orderTypes as $type) {
            DB::table('order_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 5. Order Statuses (HU-15 to HU-18)
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

        // 6. Order Item Statuses (HU-17)
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

        // 7. Receipt Types (HU-19)
        $receiptTypes = [
            ['name' => 'ticket'],
            ['name' => 'factura'],
        ];
        foreach ($receiptTypes as $type) {
            DB::table('receipt_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 8. Payment Methods (HU-19)
        $paymentMethods = [
            ['name' => 'efectivo'],
            ['name' => 'tarjeta'],
            ['name' => 'transferencia'],
            ['name' => 'mixto'],
        ];
        foreach ($paymentMethods as $method) {
            DB::table('payment_methods')->updateOrInsert(['name' => $method['name']], array_merge($method, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 9. Sale Statuses (HU-19)
        $saleStatuses = [
            ['name' => 'completada'],
            ['name' => 'anulada'],
        ];
        foreach ($saleStatuses as $status) {
            DB::table('sale_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 10. Cash Movement Types (HU-20)
        $cashMovementTypes = [
            ['name' => 'ingreso'],
            ['name' => 'egreso'],
            ['name' => 'gasto_operativo'],
        ];
        foreach ($cashMovementTypes as $type) {
            DB::table('cash_movement_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 11. Cash Movement Categories (HU-20)
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

        // 12. Delivery Days (HU-12)
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

        // 13. Purchase Request Statuses (HU-13)
        $purchaseRequestStatuses = [
            ['name' => 'pendiente'],
            ['name' => 'aprobada'],
            ['name' => 'rechazada_sin_comprar'],
            ['name' => 'procesada'],
        ];
        foreach ($purchaseRequestStatuses as $status) {
            DB::table('purchase_request_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 14. Purchase Order Statuses (HU-13)
        $purchaseOrderStatuses = [
            ['name' => 'solicitada'],
            ['name' => 'recibida_completa'],
            ['name' => 'recibida_con_incidencia'],
            ['name' => 'cancelada'],
        ];
        foreach ($purchaseOrderStatuses as $status) {
            DB::table('purchase_order_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 15. Delivery Incident Types (HU-14)
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

        // 16. Delivery Incident Statuses (HU-14)
        $deliveryIncidentStatuses = [
            ['name' => 'reportada'],
            ['name' => 'en_correccion'],
            ['name' => 'resuelta'],
        ];
        foreach ($deliveryIncidentStatuses as $status) {
            DB::table('delivery_incident_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 17. Inventory Movement Types (HU-10)
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

        // 18. Adjustment Status Types (HU-10)
        $adjustmentStatusTypes = [
            ['name' => 'aprobado'],
            ['name' => 'pendiente_aprobacion'],
            ['name' => 'rechazado'],
        ];
        foreach ($adjustmentStatusTypes as $type) {
            DB::table('adjustment_status_types')->updateOrInsert(['name' => $type['name']], array_merge($type, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 19. Alert Origins (HU-11)
        $alertOrigins = [
            ['name' => 'manual'],
            ['name' => 'automatic'],
        ];
        foreach ($alertOrigins as $origin) {
            DB::table('alert_origins')->updateOrInsert(['name' => $origin['name']], array_merge($origin, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 20. Alert Statuses (HU-11)
        $alertStatuses = [
            ['name' => 'pending'],
            ['name' => 'attended'],
        ];
        foreach ($alertStatuses as $status) {
            DB::table('alert_statuses')->updateOrInsert(['name' => $status['name']], array_merge($status, ['created_at' => $now, 'updated_at' => $now]));
        }
    }
}

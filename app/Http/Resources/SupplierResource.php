<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Supplier
 */
#[OA\Schema(
    schema: 'SupplierResource',
    title: 'Supplier Resource',
    description: 'Información completa del proveedor',
    properties: [
        new OA\Property(property: 'id', description: 'ID del proveedor', type: 'integer', example: 1),
        new OA\Property(property: 'company_name', description: 'Nombre de la empresa o razón social', type: 'string', example: 'Distribuidora Avícola'),
        new OA\Property(property: 'contact_name', description: 'Nombre de la persona de contacto', type: 'string', nullable: true, example: 'Juan Pérez'),
        new OA\Property(property: 'phone', description: 'Teléfono de contacto', type: 'string', nullable: true, example: '555-1234'),
        new OA\Property(property: 'email', description: 'Correo electrónico de contacto', type: 'string', nullable: true, example: 'contacto@avicola.com'),
        new OA\Property(property: 'address', description: 'Dirección física o fiscal', type: 'string', nullable: true, example: 'Av. Industrial 456'),
        new OA\Property(property: 'is_active', description: 'Estado activo/inactivo (baja lógica)', type: 'boolean', example: true),
        new OA\Property(property: 'delivery_days', description: 'Días habituales de entrega', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliveryDayResource')),
        new OA\Property(property: 'supplies', description: 'Insumos que abastece con precio acordado', type: 'array', items: new OA\Items(ref: '#/components/schemas/SupplierSupplyResource')),
        new OA\Property(property: 'purchase_orders_count', description: 'Cantidad de compras/entregas registradas', type: 'integer', example: 12),
        new OA\Property(property: 'delivery_incidents_count', description: 'Cantidad de incidencias registradas', type: 'integer', example: 1),
        new OA\Property(
            property: 'performance',
            description: 'Métricas de desempeño y cumplimiento del proveedor',
            type: 'object',
            properties: [
                new OA\Property(property: 'total_orders', type: 'integer', example: 12),
                new OA\Property(property: 'completed_orders', type: 'integer', example: 11),
                new OA\Property(property: 'incident_orders', type: 'integer', example: 1),
                new OA\Property(property: 'total_incidents', type: 'integer', example: 1),
                new OA\Property(property: 'compliance_rate', type: 'number', example: 91.67),
            ]
        ),
        new OA\Property(property: 'purchase_orders', description: 'Historial de compras y entregas', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseOrderResource')),
        new OA\Property(property: 'delivery_incidents', description: 'Historial de incidencias registradas', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliveryIncidentResource')),
        new OA\Property(property: 'created_at', description: 'Fecha de creación', type: 'string', format: 'date-time', example: '2026-10-07T12:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Fecha de última actualización', type: 'string', format: 'date-time', example: '2026-10-07T12:00:00Z'),
    ]
)]
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalOrders = $this->purchase_orders_count ?? ($this->relationLoaded('purchaseOrders') ? $this->purchaseOrders->count() : 0);
        $totalIncidents = $this->delivery_incidents_count ?? ($this->relationLoaded('deliveryIncidents') ? $this->deliveryIncidents->count() : 0);

        $completedOrders = $this->relationLoaded('purchaseOrders')
            ? $this->purchaseOrders->where('purchase_order_status_id', 2)->count()
            : null;

        $incidentOrders = $this->relationLoaded('purchaseOrders')
            ? $this->purchaseOrders->where('purchase_order_status_id', 3)->count()
            : null;

        $complianceRate = null;
        if ($totalOrders > 0) {
            $successfulOrders = $completedOrders ?? ($totalOrders - $totalIncidents);
            $complianceRate = round(($successfulOrders / $totalOrders) * 100, 2);
        }

        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_active' => (bool) $this->is_active,
            'delivery_days' => DeliveryDayResource::collection($this->whenLoaded('deliveryDays')),
            'supplies' => SupplierSupplyResource::collection($this->whenLoaded('supplierSupplies')),
            'purchase_orders_count' => (int) $totalOrders,
            'delivery_incidents_count' => (int) $totalIncidents,
            'performance' => [
                'total_orders' => (int) $totalOrders,
                'completed_orders' => $completedOrders !== null ? (int) $completedOrders : null,
                'incident_orders' => $incidentOrders !== null ? (int) $incidentOrders : null,
                'total_incidents' => (int) $totalIncidents,
                'compliance_rate' => $complianceRate,
            ],
            'purchase_orders' => PurchaseOrderResource::collection($this->whenLoaded('purchaseOrders')),
            'delivery_incidents' => DeliveryIncidentResource::collection($this->whenLoaded('deliveryIncidents')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

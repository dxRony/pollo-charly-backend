<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeliveryIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin DeliveryIncident
 */
#[OA\Schema(
    schema: 'DeliveryIncidentResource',
    title: 'Delivery Incident Resource',
    description: 'Incidencia registrada en la recepción de entrega de un proveedor',
    properties: [
        new OA\Property(property: 'id', description: 'ID de la incidencia', type: 'integer', example: 1),
        new OA\Property(property: 'purchase_order_id', description: 'ID de la orden de compra asociada', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'purchase_order_code', description: 'Código de la orden de compra', type: 'string', nullable: true, example: 'OC-2026-0001'),
        new OA\Property(property: 'supplier_id', description: 'ID del proveedor', type: 'integer', example: 2),
        new OA\Property(property: 'supplier_name', description: 'Nombre del proveedor', type: 'string', example: 'Distribuidora Avícola'),
        new OA\Property(property: 'receiving_user_id', description: 'ID del usuario que recibió la entrega', type: 'integer', example: 3),
        new OA\Property(property: 'receiving_user_name', description: 'Nombre del usuario receptor', type: 'string', example: 'Carlos Mesero'),
        new OA\Property(property: 'delivery_incident_type_id', description: 'ID del tipo de incidencia', type: 'integer', example: 1),
        new OA\Property(property: 'type', description: 'Tipo de incidencia', type: 'string', example: 'peso_incompleto'),
        new OA\Property(property: 'delivery_incident_status_id', description: 'ID del estado de la incidencia', type: 'integer', example: 1),
        new OA\Property(property: 'status', description: 'Estado actual de la incidencia', type: 'string', example: 'reportada'),
        new OA\Property(property: 'description', description: 'Descripción detallada de la anomalía observada', type: 'string', example: 'Faltaron 5 kg de pechuga respecto a lo facturado.'),
        new OA\Property(property: 'evidence_path', description: 'Ruta o URL de evidencia fotográfica si aplica', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'created_at', description: 'Fecha de registro', type: 'string', format: 'date-time', example: '2026-10-07T12:00:00Z'),
    ]
)]
class DeliveryIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_code' => $this->purchaseOrder?->code,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->company_name,
            'receiving_user_id' => $this->receiving_user_id,
            'receiving_user_name' => $this->receivingUser?->name,
            'delivery_incident_type_id' => $this->delivery_incident_type_id,
            'type' => $this->type?->name,
            'delivery_incident_status_id' => $this->delivery_incident_status_id,
            'status' => $this->status?->name,
            'description' => $this->description,
            'evidence_path' => $this->evidence_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

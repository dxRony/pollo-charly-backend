<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use Illuminate\Support\Collection;

final class BuildInventoryMovementsReportAction
{
    /**
     * @param  array{date_from?: string, date_to?: string, type?: string, supply_id?: int}  $filters
     * @return array{filters: array<string, mixed>, rows: Collection<int, array<string, mixed>>}
     */
    public function handle(array $filters): array
    {
        $query = InventoryMovement::query()->with(['supply', 'movementType', 'user']);

        if (! empty($filters['supply_id'])) {
            $query->where('supply_id', (int) $filters['supply_id']);
        }

        if (! empty($filters['type'])) {
            $typeMap = [
                'compra' => InventoryMovementType::COMPRA_ENTRADA,
                'salida' => InventoryMovementType::CONSUMO_VENTA,
                'merma' => InventoryMovementType::MERMA_DANO,
                'ajuste' => InventoryMovementType::AJUSTE_INVENTARIO,
                'cancelacion' => InventoryMovementType::CANCELACION_PEDIDO,
            ];
            $canonicalType = $typeMap[$filters['type']] ?? $filters['type'];

            $query->whereHas('movementType', fn ($q) => $q->where('name', $canonicalType));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $rows = $query->orderBy('created_at')->get()->map(fn (InventoryMovement $movement) => [
            'date' => $movement->created_at?->format('Y-m-d H:i'),
            'supply_name' => $movement->supply?->name,
            'type' => $movement->movementType?->name,
            'quantity' => (float) $movement->quantity,
            'previous_stock' => (float) $movement->previous_stock,
            'new_stock' => (float) $movement->new_stock,
            'user_name' => $movement->user?->name,
            'reason' => $movement->reason,
        ]);

        return [
            'filters' => $filters,
            'rows' => $rows,
        ];
    }
}

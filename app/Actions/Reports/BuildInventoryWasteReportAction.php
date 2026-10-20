<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\AdjustmentStatusType;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use Illuminate\Support\Collection;

final class BuildInventoryWasteReportAction
{
    /**
     * @param  array{date_from?: string, date_to?: string, supply_id?: int, user_id?: int}  $filters
     * @return array{
     *     filters: array<string, mixed>,
     *     summary: array{total_loss_cost: float, total_quantity: float, records_count: int, top_wasted_supply: string|null},
     *     by_supply: Collection<int, array<string, mixed>>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters): array
    {
        $wasteTypeNames = [
            InventoryMovementType::MERMA_DANO,
            InventoryMovementType::AJUSTE_MERMA,
            InventoryMovementType::AJUSTE_FALTANTE,
        ];

        $approvedStatus = AdjustmentStatusType::query()
            ->where('name', AdjustmentStatusType::APROBADO)
            ->first();

        $query = InventoryMovement::query()
            ->with([
                'supply.measurementUnit',
                'movementType',
                'user',
                'approverUser',
                'adjustmentStatus',
            ])
            ->where(function ($sub) use ($wasteTypeNames, $approvedStatus) {
                // Mermas directas y ajustes de merma/faltante
                $sub->whereHas('movementType', fn ($q) => $q->whereIn('name', $wasteTypeNames))
                    // O ajustes de inventario donde hubo reducción física (pérdida/faltante)
                    ->orWhere(function ($adjQuery) use ($approvedStatus) {
                        $adjQuery->whereHas('movementType', fn ($q) => $q->where('name', InventoryMovementType::AJUSTE_INVENTARIO))
                            ->whereColumn('new_stock', '<', 'previous_stock')
                            ->where(function ($statusQuery) use ($approvedStatus) {
                                // Aprobados o sin estado asignado (ajustes históricos directos)
                                if ($approvedStatus) {
                                    $statusQuery->where('adjustment_status_type_id', $approvedStatus->id)
                                        ->orWhereNull('adjustment_status_type_id');
                                } else {
                                    $statusQuery->whereNull('adjustment_status_type_id');
                                }
                            });
                    });
            });

        if (! empty($filters['supply_id'])) {
            $query->where('supply_id', (int) $filters['supply_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $movements = $query->orderBy('created_at', 'desc')->get();

        $rows = $movements->map(function (InventoryMovement $movement): array {
            $typeName = $movement->movementType?->name;

            // Determinar cantidad perdida
            if ($typeName === InventoryMovementType::AJUSTE_INVENTARIO) {
                $lossQty = round(abs((float) $movement->previous_stock - (float) $movement->new_stock), 2);
            } else {
                $lossQty = round((float) $movement->quantity, 2);
            }

            $unitCost = round((float) ($movement->supply?->unit_cost ?? 0.0), 2);
            $totalCost = round($lossQty * $unitCost, 2);

            $unit = $movement->supply?->measurementUnit?->abbreviation
                ?? $movement->supply?->measurementUnit?->name
                ?? 'unid';

            return [
                'id' => $movement->id,
                'date' => $movement->created_at?->format('Y-m-d H:i'),
                'supply_id' => $movement->supply_id,
                'supply_name' => $movement->supply?->name ?? '—',
                'unit' => $unit,
                'type' => $this->labelWasteType($typeName),
                'quantity' => $lossQty,
                'previous_stock' => (float) $movement->previous_stock,
                'new_stock' => (float) $movement->new_stock,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'user_name' => $movement->user?->name ?? '—',
                'approver_name' => $movement->approverUser?->name,
                'reason' => $movement->reason ?? '—',
            ];
        });

        $totalLossCost = round((float) $rows->sum('total_cost'), 2);
        $totalQuantity = round((float) $rows->sum('quantity'), 2);
        $recordsCount = $rows->count();

        // Agrupación por insumo
        $bySupply = $rows->groupBy('supply_name')->map(function (Collection $items, string $supplyName): array {
            $first = $items->first();

            return [
                'supply_name' => $supplyName,
                'unit' => $first['unit'] ?? 'unid',
                'total_quantity' => round((float) $items->sum('quantity'), 2),
                'total_cost' => round((float) $items->sum('total_cost'), 2),
                'records_count' => $items->count(),
            ];
        })->values()->sortByDesc('total_cost')->values();

        $topWastedSupply = $bySupply->first()['supply_name'] ?? null;

        return [
            'filters' => $filters,
            'summary' => [
                'total_loss_cost' => $totalLossCost,
                'total_quantity' => $totalQuantity,
                'records_count' => $recordsCount,
                'top_wasted_supply' => $topWastedSupply,
            ],
            'by_supply' => $bySupply,
            'rows' => $rows,
        ];
    }

    private function labelWasteType(?string $name): string
    {
        return match ($name) {
            InventoryMovementType::MERMA_DANO => 'Merma / Daño',
            InventoryMovementType::AJUSTE_MERMA => 'Ajuste por merma',
            InventoryMovementType::AJUSTE_FALTANTE => 'Ajuste por faltante',
            InventoryMovementType::AJUSTE_INVENTARIO => 'Ajuste físico (faltante)',
            default => 'Merma / Pérdida',
        };
    }
}

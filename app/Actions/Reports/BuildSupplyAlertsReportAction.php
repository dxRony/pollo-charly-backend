<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\SupplyAlert;
use Illuminate\Support\Collection;

final class BuildSupplyAlertsReportAction
{
    /**
     * @param  array{date_from?: string, date_to?: string, status?: string, origin?: string, supply_id?: int}  $filters
     * @return array{filters: array<string, mixed>, rows: Collection<int, array<string, mixed>>}
     */
    public function handle(array $filters): array
    {
        $query = SupplyAlert::query()->with(['supply', 'origin', 'status', 'user']);

        if (! empty($filters['supply_id'])) {
            $query->where('supply_id', (int) $filters['supply_id']);
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];
            $query->whereHas('status', fn ($q) => $q->where('name', $status));
        }

        if (! empty($filters['origin'])) {
            $origin = $filters['origin'];
            $query->whereHas('origin', fn ($q) => $q->where('name', $origin));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $rows = $query->orderBy('created_at')->get()->map(fn (SupplyAlert $alert) => [
            'date' => $alert->created_at?->format('Y-m-d H:i'),
            'supply_name' => $alert->supply?->name,
            'origin' => $alert->origin?->name,
            'status' => $alert->status?->name,
            'user_name' => $alert->user?->name,
            'notes' => $alert->notes,
        ]);

        return [
            'filters' => $filters,
            'rows' => $rows,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use Illuminate\Support\Collection;

final class BuildSupplierPurchasesReportAction
{
    /**
     * @param  array{date_from?: string, date_to?: string, supplier_id?: int, status?: string}  $filters
     * @return array{
     *     filters: array<string, mixed>,
     *     summary: array{
     *         total_purchases_amount: float,
     *         orders_count: int,
     *         completed_orders_count: int,
     *         incident_orders_count: int,
     *         total_incidents: int,
     *         fulfillment_rate: float,
     *         top_supplier: string|null
     *     },
     *     by_supplier: Collection<int, array<string, mixed>>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters): array
    {
        $query = PurchaseOrder::query()
            ->with([
                'supplier',
                'status',
                'items',
                'deliveryIncidents.type',
            ]);

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];
            $query->whereHas('status', fn ($q) => $q->where('name', $status));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $rows = $orders->map(function (PurchaseOrder $order): array {
            $statusName = $order->status?->name;
            $incidents = $order->deliveryIncidents;
            $incidentsCount = $incidents->count();

            $punctuality = 'Pendiente';
            if ($statusName === PurchaseOrderStatus::CANCELADA) {
                $punctuality = 'Cancelada';
            } elseif ($order->received_date && $order->expected_date) {
                $punctuality = $order->received_date->lte($order->expected_date) ? 'A tiempo' : 'Tarde';
            } elseif ($order->received_date) {
                $punctuality = 'Recibida';
            }

            $incidentsSummary = $incidents->map(function ($incident): string {
                return $incident->type?->name
                    ? ucfirst(str_replace('_', ' ', (string) $incident->type->name))
                    : 'Incidencia';
            })->unique()->implode(', ');

            return [
                'id' => $order->id,
                'code' => $order->code,
                'date' => $order->created_at?->format('Y-m-d') ?? '—',
                'supplier_id' => $order->supplier_id,
                'supplier_name' => $order->supplier?->company_name ?? '—',
                'status' => $statusName,
                'status_label' => $this->labelStatus($statusName),
                'expected_date' => $order->expected_date?->format('Y-m-d') ?? '—',
                'received_date' => $order->received_date?->format('Y-m-d') ?? '—',
                'punctuality' => $punctuality,
                'items_count' => $order->items->count(),
                'total' => (float) $order->total,
                'incidents_count' => $incidentsCount,
                'incidents_summary' => $incidentsSummary ?: 'Ninguna',
            ];
        });

        // Totales y KPIs
        $validOrders = $rows->where('status', '!=', PurchaseOrderStatus::CANCELADA);
        $totalPurchasesAmount = round((float) $validOrders->sum('total'), 2);
        $ordersCount = $rows->count();
        $completedOrdersCount = $rows->where('status', PurchaseOrderStatus::RECIBIDA_COMPLETA)->count();
        $incidentOrdersCount = $rows->filter(function (array $r): bool {
            return $r['status'] === PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA || $r['incidents_count'] > 0;
        })->count();
        $totalIncidents = (int) $rows->sum('incidents_count');

        $receivedTotal = $completedOrdersCount + $incidentOrdersCount;
        $fulfillmentRate = $receivedTotal > 0
            ? round(($completedOrdersCount / $receivedTotal) * 100, 1)
            : 100.0;

        // Desglose por proveedor
        $bySupplier = $rows->groupBy('supplier_id')->map(function (Collection $supplierRows): array {
            $first = $supplierRows->first();
            $valid = $supplierRows->where('status', '!=', PurchaseOrderStatus::CANCELADA);
            $totalSpent = round((float) $valid->sum('total'), 2);
            $supplierOrdersCount = $supplierRows->count();
            $completed = $supplierRows->where('status', PurchaseOrderStatus::RECIBIDA_COMPLETA)->count();
            $incidents = $supplierRows->filter(fn (array $r) => $r['status'] === PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA || $r['incidents_count'] > 0)->count();
            $totalInc = (int) $supplierRows->sum('incidents_count');

            $delivered = $supplierRows->filter(fn (array $r) => in_array($r['punctuality'], ['A tiempo', 'Tarde'], true));
            $onTime = $delivered->where('punctuality', 'A tiempo')->count();
            $punctualityRate = $delivered->count() > 0
                ? round(($onTime / $delivered->count()) * 100, 1)
                : 100.0;

            $received = $completed + $incidents;
            $fulfillment = $received > 0
                ? round(($completed / $received) * 100, 1)
                : 100.0;

            return [
                'supplier_id' => $first['supplier_id'],
                'supplier_name' => $first['supplier_name'],
                'orders_count' => $supplierOrdersCount,
                'total_spent' => $totalSpent,
                'completed_count' => $completed,
                'incident_count' => $incidents,
                'total_incidents' => $totalInc,
                'fulfillment_rate' => $fulfillment,
                'punctuality_rate' => $punctualityRate,
            ];
        })->values()->sortByDesc('total_spent')->values();

        $topSupplier = $bySupplier->first()['supplier_name'] ?? null;

        return [
            'filters' => $filters,
            'summary' => [
                'total_purchases_amount' => $totalPurchasesAmount,
                'orders_count' => $ordersCount,
                'completed_orders_count' => $completedOrdersCount,
                'incident_orders_count' => $incidentOrdersCount,
                'total_incidents' => $totalIncidents,
                'fulfillment_rate' => $fulfillmentRate,
                'top_supplier' => $topSupplier,
            ],
            'by_supplier' => $bySupplier,
            'rows' => $rows,
        ];
    }

    private function labelStatus(?string $status): string
    {
        return match ($status) {
            PurchaseOrderStatus::SOLICITADA => 'Solicitada',
            PurchaseOrderStatus::RECIBIDA_COMPLETA => 'Recibida completa',
            PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA => 'Con incidencia',
            PurchaseOrderStatus::CANCELADA => 'Cancelada',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }
}

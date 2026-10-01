<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\OrderItemStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BuildTopDishesReportAction
{
    /**
     * Calcula el ranking de platillos más vendidos a partir de ventas ya cerradas
     * (order_items cuya comanda tiene una venta registrada), no de comandas en curso.
     *
     * @param  array{date_from?: string, date_to?: string, category_id?: int, limit?: int}  $filters
     * @return array{filters: array<string, mixed>, rows: Collection<int, array<string, mixed>>}
     */
    public function handle(array $filters): array
    {
        $limit = (int) ($filters['limit'] ?? 10);

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('sales', 'sales.order_id', '=', 'orders.id')
            ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
            ->join('order_item_statuses', 'order_items.order_item_status_id', '=', 'order_item_statuses.id')
            ->leftJoin('categories', 'dishes.category_id', '=', 'categories.id')
            ->whereNotIn('order_item_statuses.name', [OrderItemStatus::ELIMINADO, OrderItemStatus::CANCELADO]);

        if (! empty($filters['date_from'])) {
            $query->whereDate('sales.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('sales.created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('dishes.category_id', (int) $filters['category_id']);
        }

        $rows = $query
            ->groupBy('dishes.id', 'dishes.name', 'categories.name')
            ->select([
                'dishes.id as dish_id',
                'dishes.name as dish_name',
                'categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'dish_id' => (int) $row->dish_id,
                'dish_name' => $row->dish_name,
                'category_name' => $row->category_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        return [
            'filters' => $filters,
            'rows' => $rows,
        ];
    }
}

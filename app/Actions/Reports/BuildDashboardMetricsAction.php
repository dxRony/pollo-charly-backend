<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\AlertStatus;
use App\Models\OrderStatus;
use App\Models\Sale;
use App\Models\SupplyAlert;
use App\Models\TableStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

final class BuildDashboardMetricsAction
{
    public function __construct(
        private readonly BuildTopDishesReportAction $buildTopDishes,
    ) {}

    /**
     * @param  array{date_from?: string, date_to?: string}  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::now()->subDays(29)->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();

        $salesQuery = Sale::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        $totalSales = (float) (clone $salesQuery)->sum('total');
        $salesCount = (clone $salesQuery)->count();

        $activeOrdersCount = DB::table('orders')
            ->join('order_statuses', 'orders.order_status_id', '=', 'order_statuses.id')
            ->whereIn('order_statuses.name', [OrderStatus::PENDIENTE, OrderStatus::EN_PREPARACION, OrderStatus::LISTA])
            ->count();

        $occupiedTablesCount = DB::table('restaurant_tables')
            ->join('table_statuses', 'restaurant_tables.table_status_id', '=', 'table_statuses.id')
            ->where('table_statuses.name', TableStatus::OCUPADA)
            ->count();

        $pendingAlertsCount = SupplyAlert::query()
            ->whereHas('status', fn ($q) => $q->where('name', AlertStatus::PENDING))
            ->count();

        $salesByDay = (clone $salesQuery)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->count,
            ]);

        $topDishes = $this->buildTopDishes->handle([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => 5,
        ])['rows'];

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'kpis' => [
                'total_sales' => round($totalSales, 2),
                'sales_count' => $salesCount,
                'average_ticket' => $salesCount > 0 ? round($totalSales / $salesCount, 2) : 0.0,
                'active_orders_count' => $activeOrdersCount,
                'occupied_tables_count' => $occupiedTablesCount,
                'pending_alerts_count' => $pendingAlertsCount,
                'top_dish_name' => $topDishes->first()['dish_name'] ?? null,
            ],
            'sales_by_day' => $salesByDay,
            'top_dishes' => $topDishes->values(),
        ];
    }
}

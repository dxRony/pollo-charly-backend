<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\Sale;
use Illuminate\Support\Collection;

final class BuildSalesReportAction
{
    /**
     * @param  array{date_from?: string, date_to?: string, cashier_user_id?: int, payment_method?: string}  $filters
     * @return array{filters: array<string, mixed>, summary: array<string, mixed>, rows: Collection<int, array<string, mixed>>}
     */
    public function handle(array $filters): array
    {
        $query = Sale::query()->with([
            'order.restaurantTable',
            'cashier',
            'paymentMethod',
            'receiptType',
        ]);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['cashier_user_id'])) {
            $query->where('cashier_user_id', (int) $filters['cashier_user_id']);
        }

        if (! empty($filters['payment_method'])) {
            $paymentMethod = $filters['payment_method'];
            $query->whereHas('paymentMethod', function ($q) use ($paymentMethod) {
                $q->where('name', $paymentMethod);
            });
        }

        $sales = $query->orderBy('created_at')->get();

        $rows = $sales->map(fn (Sale $sale) => [
            'receipt_number' => $sale->receipt_number,
            'date' => $sale->created_at?->format('Y-m-d H:i'),
            'order_code' => $sale->order?->code,
            'table' => $sale->order?->restaurantTable?->number,
            'cashier_name' => $sale->cashier?->name,
            'payment_method' => $sale->paymentMethod?->name,
            'receipt_type' => $sale->receiptType?->name,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'total' => (float) $sale->total,
        ]);

        $totalAmount = (float) $rows->sum('total');
        $count = $rows->count();

        return [
            'filters' => $filters,
            'summary' => [
                'total_sales' => round($totalAmount, 2),
                'sales_count' => $count,
                'average_ticket' => $count > 0 ? round($totalAmount / $count, 2) : 0.0,
            ],
            'rows' => $rows,
        ];
    }
}

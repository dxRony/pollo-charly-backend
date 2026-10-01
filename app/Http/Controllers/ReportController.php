<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reports\BuildDashboardMetricsAction;
use App\Actions\Reports\BuildInventoryMovementsReportAction;
use App\Actions\Reports\BuildSalesReportAction;
use App\Actions\Reports\BuildSupplyAlertsReportAction;
use App\Actions\Reports\BuildTopDishesReportAction;
use App\Exports\GenericTableExport;
use App\Models\AlertOrigin;
use App\Models\AlertStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    #[OA\Get(
        path: '/api/reports/dashboard',
        operationId: 'getDashboardMetrics',
        description: 'Obtiene los indicadores clave (KPIs), ventas por día y platillos más vendidos del periodo, para el panel de métricas del Administrador.',
        summary: 'Panel de métricas',
        security: [['bearerAuth' => []]],
        tags: ['Reportes'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Fecha inicial (por defecto, 30 días atrás)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Fecha final (por defecto, hoy)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Métricas obtenidas exitosamente.'),
            new OA\Response(response: 403, description: 'No autorizado. Se requiere rol de Administrador.'),
        ]
    )]
    public function dashboard(Request $request, BuildDashboardMetricsAction $action): JsonResponse
    {
        $metrics = $action->handle($request->only(['date_from', 'date_to']));

        return response()->json($metrics);
    }

    #[OA\Get(
        path: '/api/reports/sales',
        operationId: 'getSalesReport',
        description: 'Genera el reporte de ventas del periodo filtrado, con resumen de totales. Soporta exportación a PDF y Excel mediante el parámetro format.',
        summary: 'Reporte de ventas',
        security: [['bearerAuth' => []]],
        tags: ['Reportes'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'cashier_user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'payment_method', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'format', in: 'query', description: 'json (por defecto), pdf o xlsx', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte de ventas obtenido exitosamente.'),
            new OA\Response(response: 403, description: 'No autorizado. Se requiere rol de Administrador.'),
        ]
    )]
    public function sales(Request $request, BuildSalesReportAction $action): JsonResponse|Response
    {
        $report = $action->handle($request->only(['date_from', 'date_to', 'cashier_user_id', 'payment_method']));

        $format = (string) $request->query('format', 'json');

        if ($format === 'json') {
            return response()->json($report);
        }

        $columns = ['N° Comprobante', 'Fecha', 'Comanda', 'Mesa', 'Cajero', 'Método de pago', 'Comprobante', 'Subtotal', 'Descuento', 'Total'];

        $exportRows = $report['rows']->map(fn (array $row) => [
            $row['receipt_number'],
            $row['date'],
            $row['order_code'],
            $row['table'] ?? '—',
            $row['cashier_name'],
            $this->labelPaymentMethod($row['payment_method']),
            ucfirst((string) $row['receipt_type']),
            $this->money((float) $row['subtotal']),
            $this->money((float) $row['discount']),
            $this->money((float) $row['total']),
        ]);

        $summary = [
            'Total vendido' => $this->money((float) $report['summary']['total_sales']),
            'N° de ventas' => (string) $report['summary']['sales_count'],
            'Ticket promedio' => $this->money((float) $report['summary']['average_ticket']),
        ];

        return $this->export($format, 'Reporte de Ventas', $this->describeFilters($report['filters']), $columns, $exportRows, $summary, 'reporte-ventas');
    }

    #[OA\Get(
        path: '/api/reports/top-dishes',
        operationId: 'getTopDishesReport',
        description: 'Genera el ranking de platillos más vendidos en el periodo filtrado, a partir de ventas ya cerradas. Soporta exportación a PDF y Excel.',
        summary: 'Reporte de platillos más vendidos',
        security: [['bearerAuth' => []]],
        tags: ['Reportes'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte obtenido exitosamente.'),
            new OA\Response(response: 403, description: 'No autorizado. Se requiere rol de Administrador.'),
        ]
    )]
    public function topDishes(Request $request, BuildTopDishesReportAction $action): JsonResponse|Response
    {
        $report = $action->handle($request->only(['date_from', 'date_to', 'category_id', 'limit']));

        $format = (string) $request->query('format', 'json');

        if ($format === 'json') {
            return response()->json($report);
        }

        $columns = ['Platillo', 'Categoría', 'Cantidad vendida', 'Ingresos generados'];

        $exportRows = $report['rows']->map(fn (array $row) => [
            $row['dish_name'],
            $row['category_name'] ?? '—',
            $row['quantity_sold'],
            $this->money((float) $row['revenue']),
        ]);

        return $this->export($format, 'Platillos Más Vendidos', $this->describeFilters($report['filters']), $columns, $exportRows, null, 'reporte-platillos-mas-vendidos');
    }

    #[OA\Get(
        path: '/api/reports/inventory-movements',
        operationId: 'getInventoryMovementsReport',
        description: 'Genera el reporte de movimientos de inventario del periodo filtrado. Soporta exportación a PDF y Excel.',
        summary: 'Reporte de movimientos de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Reportes'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'supply_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte obtenido exitosamente.'),
            new OA\Response(response: 403, description: 'No autorizado. Se requiere rol de Administrador.'),
        ]
    )]
    public function inventoryMovements(Request $request, BuildInventoryMovementsReportAction $action): JsonResponse|Response
    {
        $report = $action->handle($request->only(['date_from', 'date_to', 'type', 'supply_id']));

        $format = (string) $request->query('format', 'json');

        if ($format === 'json') {
            return response()->json($report);
        }

        $columns = ['Fecha', 'Insumo', 'Tipo', 'Cantidad', 'Existencia previa', 'Existencia nueva', 'Usuario', 'Motivo'];

        $exportRows = $report['rows']->map(fn (array $row) => [
            $row['date'],
            $row['supply_name'],
            $this->labelMovementType((string) $row['type']),
            $row['quantity'],
            $row['previous_stock'],
            $row['new_stock'],
            $row['user_name'],
            $row['reason'] ?? '—',
        ]);

        return $this->export($format, 'Movimientos de Inventario', $this->describeFilters($report['filters']), $columns, $exportRows, null, 'reporte-movimientos-inventario');
    }

    #[OA\Get(
        path: '/api/reports/supply-alerts',
        operationId: 'getSupplyAlertsReport',
        description: 'Genera el reporte de alertas de reposición del periodo filtrado. Soporta exportación a PDF y Excel.',
        summary: 'Reporte de alertas de reposición',
        security: [['bearerAuth' => []]],
        tags: ['Reportes'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'origin', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'supply_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte obtenido exitosamente.'),
            new OA\Response(response: 403, description: 'No autorizado. Se requiere rol de Administrador.'),
        ]
    )]
    public function supplyAlerts(Request $request, BuildSupplyAlertsReportAction $action): JsonResponse|Response
    {
        $report = $action->handle($request->only(['date_from', 'date_to', 'status', 'origin', 'supply_id']));

        $format = (string) $request->query('format', 'json');

        if ($format === 'json') {
            return response()->json($report);
        }

        $columns = ['Fecha', 'Insumo', 'Origen', 'Estado', 'Usuario', 'Notas'];

        $exportRows = $report['rows']->map(fn (array $row) => [
            $row['date'],
            $row['supply_name'],
            $row['origin'] === AlertOrigin::MANUAL ? 'Manual' : 'Automática',
            $row['status'] === AlertStatus::PENDING ? 'Pendiente' : 'Atendida',
            $row['user_name'] ?? '—',
            $row['notes'] ?? '—',
        ]);

        return $this->export($format, 'Alertas de Reposición', $this->describeFilters($report['filters']), $columns, $exportRows, null, 'reporte-alertas-reposicion');
    }

    /**
     * @param  array<int, string>  $columns
     * @param  Collection<int, array<int, mixed>>  $rows
     * @param  array<string, string>|null  $summary
     */
    private function export(string $format, string $title, string $subtitle, array $columns, Collection $rows, ?array $summary, string $fileSlug): Response
    {
        if ($format === 'xlsx') {
            $headingsWithData = $rows->map(fn (array $row) => array_combine($columns, $row));

            return Excel::download(
                new GenericTableExport($columns, $headingsWithData, $title),
                "{$fileSlug}.xlsx",
            );
        }

        $pdf = Pdf::loadView('reports.generic', [
            'title' => $title,
            'subtitle' => $subtitle,
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $summary,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('letter', 'landscape');

        return $pdf->download("{$fileSlug}.pdf");
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function describeFilters(array $filters): string
    {
        $parts = [];

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $parts[] = 'Del ' . ($filters['date_from'] ?? '—') . ' al ' . ($filters['date_to'] ?? 'hoy');
        }

        foreach ($filters as $key => $value) {
            if (in_array($key, ['date_from', 'date_to'], true) || empty($value)) {
                continue;
            }
            $parts[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return count($parts) > 0 ? implode(' · ', $parts) : 'Sin filtros aplicados';
    }

    private function money(float $amount): string
    {
        return 'Q' . number_format($amount, 2);
    }

    private function labelPaymentMethod(?string $name): string
    {
        return match ($name) {
            'efectivo' => 'Efectivo',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
            default => ucfirst((string) $name),
        };
    }

    private function labelMovementType(string $name): string
    {
        return match ($name) {
            'compra_entrada' => 'Compra',
            'consumo_venta' => 'Salida por venta',
            'merma_dano' => 'Merma',
            'ajuste_inventario' => 'Ajuste',
            'cancelacion_pedido' => 'Cancelación de pedido',
            default => ucfirst(str_replace('_', ' ', $name)),
        };
    }
}

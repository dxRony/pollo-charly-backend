<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Models\AlertStatus;
use App\Models\Complement;
use App\Models\DeliveryIncidentType;
use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RestaurantTable;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\User;
use App\Support\Demo\DemoApi;
use App\Support\Demo\DemoCatalog;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoRandom;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Simula día por día la operación del restaurante: comandas que pasan por cocina y caja, consumo de insumos,
 * alertas de stock bajo, solicitudes y órdenes de compra, recepciones (algunas con incidencia), mermas y ajustes.
 *
 * Todo se ejecuta a través del API real, con la fecha del sistema desplazada al momento simulado, de modo que las
 * fechas quedan repartidas en el pasado y el historial de cada insumo es cronológicamente coherente.
 */
final class SimulateDemoOperationsAction
{
    /** Horario de atención simulado (hora local). */
    private const OPEN_HOUR = 8;

    private const OPEN_MINUTE = 30;

    private const CLOSE_HOUR = 17;

    private const CLOSE_MINUTE = 30;

    /** Un ciclo completo de comanda (crear, preparar, listo, cobrar) dura como máximo unos 26 minutos. */
    private const MIN_SLOT_MINUTES = 27;

    /** Comandas base por día de la semana (0 = domingo). */
    private const BASE_ORDERS = [0 => 13, 1 => 5, 2 => 5, 3 => 6, 4 => 7, 5 => 11, 6 => 15];

    private DemoContext $context;

    private DemoRandom $random;

    private Closure $progress;

    /** @var array<int, array{0: CarbonImmutable, 1: Closure}> */
    private array $queue = [];

    private ?CarbonImmutable $lastMoment = null;

    private CarbonImmutable $limit;

    /** @var array<string, int|float> */
    private array $stats = [
        'orders' => 0, 'cancelled' => 0, 'out_of_stock' => 0, 'sales' => 0, 'revenue' => 0.0,
        'purchase_orders' => 0, 'incidents' => 0, 'wastes' => 0, 'adjustments' => 0,
    ];

    /** @var array<string, int> */
    private array $dishIds = [];

    /** @var array<string, float> */
    private array $dishPrices = [];

    /** @var array<string, int> */
    private array $complementIds = [];

    /** @var array<string, float> */
    private array $complementPrices = [];

    /** @var array<int, int> */
    private array $tableIds = [];

    /** @var array<string, int> código de insumo => id de proveedor */
    private array $supplierBySupply = [];

    /** @var array<string, float> código de insumo => precio acordado */
    private array $agreedPrices = [];

    public function __construct(private readonly DemoApi $api) {}

    /**
     * @param  CarbonImmutable  $realNow  Momento real de la ejecución (antes de desplazar la fecha del sistema).
     * @return array<string, int|float>
     */
    public function handle(DemoContext $context, int $days, DemoRandom $random, CarbonImmutable $realNow, Closure $progress): array
    {
        $this->context = $context;
        $this->random = $random;
        $this->progress = $progress;

        $this->loadReferences();

        $today = $realNow->setTimezone(DemoContext::TIMEZONE)->startOfDay();

        for ($offset = $days; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);
            $isToday = $offset === 0;

            $this->limit = $isToday ? $realNow->subMinutes(30) : $day->setTime(23, 59);
            $this->simulateDay($day, $days, $offset);

            if ($offset % 5 === 0 || $isToday) {
                ($this->progress)(sprintf('Día %s simulado (%d comandas, %d ventas hasta ahora)', $day->format('Y-m-d'), $this->stats['orders'], $this->stats['sales']));
            }
        }

        $this->setNow(null);

        return $this->stats;
    }

    /**
     * Deja a la vista tareas pendientes (para mostrar los flujos de aprobación y recepción): una solicitud de compra por
     * aprobar, una orden de compra por recibir, dos alertas de reposición por atender y un ajuste de inventario por
     * revisar. Se ejecuta con la fecha real.
     */
    public function leaveShowcasePendings(DemoContext $context, DemoRandom $random): void
    {
        $this->context = $context;
        $this->random = $random;
        $this->setNow(null);

        $codes = ['KET', 'MAY', 'PAN', 'ZAN'];
        $supplyIds = Supply::query()->whereIn('code', $codes)->pluck('id', 'code');

        $this->api->call($context->admin, 'POST', '/purchase-requests', [
            'reason' => 'Reposición preventiva para el fin de semana',
            'items' => [
                ['supply_id' => $supplyIds['KET'], 'suggested_quantity' => 6],
                ['supply_id' => $supplyIds['MAY'], 'suggested_quantity' => 4],
            ],
        ]);

        $this->api->call($context->admin, 'POST', '/purchase-requests', [
            'reason' => 'Verduras para la próxima entrega',
            'items' => [
                ['supply_id' => $supplyIds['ZAN'], 'suggested_quantity' => 8],
            ],
        ]);
        $requestId = (int) PurchaseRequest::query()->latest('id')->value('id');
        $supplierId = (int) Supplier::query()->where('company_name', 'Verduras y Frutas El Campo')->value('id');

        $this->api->call($context->admin, 'POST', "/purchase-requests/{$requestId}/approve", [
            'supplier_id' => $supplierId,
            'expected_date' => CarbonImmutable::now(DemoContext::TIMEZONE)->addDay()->toDateString(),
        ]);

        // Alertas reportadas por cocina que siguen pendientes de atender.
        foreach ([
            ['SAZ', 'Quedan pocas bolsas de sazonador; conviene pedir antes del fin de semana'],
            ['FLA', 'El flan se está acabando, quedan muy pocas porciones'],
        ] as [$code, $notes]) {
            $this->api->call($this->random->pick($context->cooks), 'POST', '/supply-alerts', [
                'supply_id' => Supply::query()->where('code', $code)->value('id'),
                'notes' => $notes,
            ]);
        }

        $supply = Supply::query()->where('code', 'ACE')->firstOrFail();
        $this->api->call($context->cooks[0], 'POST', '/inventory-movements', [
            'supply_id' => $supply->id,
            'type' => 'ajuste_inventario',
            'new_stock' => max(0, round((float) $supply->current_stock - 3, 2)),
            'reason' => 'Conteo físico semanal: diferencia por derrame en bodega',
        ]);
    }

    private function loadReferences(): void
    {
        foreach (Dish::query()->get() as $dish) {
            $this->dishIds[$dish->name] = $dish->id;
            $this->dishPrices[$dish->name] = (float) $dish->price;
        }

        foreach (Complement::query()->get() as $complement) {
            $this->complementIds[$complement->name] = $complement->id;
            $this->complementPrices[$complement->name] = (float) $complement->extra_price;
        }

        $this->tableIds = RestaurantTable::query()->orderBy('number')->pluck('id')->all();

        $suppliers = Supplier::query()->pluck('id', 'company_name');

        foreach (DemoCatalog::suppliers() as $supplier) {
            foreach ($supplier['supplies'] as $code => $price) {
                $this->supplierBySupply[$code] = $suppliers[$supplier['company']];
                $this->agreedPrices[$code] = $price;
            }
        }
    }

    private function simulateDay(CarbonImmutable $day, int $totalDays, int $offset): void
    {
        $this->queue = [];

        $this->schedule($day->setTime(7, 45), fn () => $this->restockFromAlerts($day));

        if ($this->random->chance(18)) {
            $this->schedule($day->setTime(17, 40), fn () => $this->registerWaste());
        }

        // Dos revisiones de ajuste de inventario en el historial (una aprobada y una rechazada), ubicadas de forma
        // proporcional: con 30 días de historial caen 20 y 12 días atrás.
        if ($totalDays >= 6 && $offset === (int) round($totalDays * 2 / 3)) {
            $this->schedule($day->setTime(17, 45), fn () => $this->requestAndReviewAdjustment(approve: true));
        }
        if ($totalDays >= 6 && $offset === (int) round($totalDays * 0.4)) {
            $this->schedule($day->setTime(17, 45), fn () => $this->requestAndReviewAdjustment(approve: false));
        }

        $this->planOrders($day);

        while ($this->queue !== []) {
            usort($this->queue, fn (array $a, array $b) => $a[0] <=> $b[0]);
            [$moment, $action] = array_shift($this->queue);
            $this->at($moment);
            $action();
        }
    }

    private function schedule(CarbonImmutable $moment, Closure $action): void
    {
        if ($moment <= $this->limit) {
            $this->queue[] = [$moment, $action];
        }
    }

    /** Reparte las comandas del día en franjas que no se solapan dentro del horario de atención. */
    private function planOrders(CarbonImmutable $day): void
    {
        $open = $day->setTime(self::OPEN_HOUR, self::OPEN_MINUTE);
        $close = $day->setTime(self::CLOSE_HOUR, self::CLOSE_MINUTE);
        $end = $close < $this->limit ? $close : $this->limit;

        $windowMinutes = (int) $open->diffInMinutes($end, false);
        $fullMinutes = (int) $open->diffInMinutes($close);

        if ($windowMinutes < self::MIN_SLOT_MINUTES) {
            return;
        }

        $count = self::BASE_ORDERS[$day->dayOfWeek] + $this->random->int(-1, 2);
        $count = (int) round($count * min(1, $windowMinutes / $fullMinutes));
        $count = max(1, min($count, intdiv($windowMinutes, self::MIN_SLOT_MINUTES)));

        $slot = $windowMinutes / $count;

        for ($i = 0; $i < $count; $i++) {
            $offsetMinutes = (int) floor($i * $slot) + $this->random->int(0, max(0, (int) floor($slot) - self::MIN_SLOT_MINUTES));
            $start = $open->addMinutes($offsetMinutes);
            $cancelled = $this->random->chance(4);

            $this->schedule($start, fn () => $this->runOrder($start, $cancelled));
        }
    }

    private function runOrder(CarbonImmutable $start, bool $cancelled): void
    {
        $waiter = $this->random->pick($this->context->waiters);
        $cook = $this->random->pick($this->context->cooks);
        [$payload, $subtotal] = $this->buildOrder();

        [$status, $body] = $this->api->send($waiter, 'POST', '/orders', $payload);

        if ($status === 422 && isset($body['insufficient_supplies'])) {
            $this->stats['out_of_stock']++;

            return;
        }

        if ($status >= 400) {
            throw new \RuntimeException("No se pudo crear la comanda ({$status}): ".json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $this->stats['orders']++;
        $orderId = (int) $body['order']['id'];

        if ($cancelled) {
            $this->at($start->addMinutes($this->random->int(3, 7)));
            $this->api->call($waiter, 'POST', "/orders/{$orderId}/cancel", [
                'cancellation_reason' => $this->random->pick(DemoCatalog::cancellationReasons()),
            ]);
            $this->stats['cancelled']++;

            return;
        }

        $this->at($start->addMinutes($this->random->int(2, 4)));
        $this->api->call($cook, 'POST', "/orders/{$orderId}/start-preparation");

        $this->at($start->addMinutes($this->random->int(9, 16)));
        $this->api->call($cook, 'POST', "/orders/{$orderId}/ready");

        $this->at($start->addMinutes($this->random->int(18, 25)));
        $this->payOrder($waiter, $orderId, $subtotal);
    }

    /**
     * @return array{0: array<string, mixed>, 1: float} Datos de la comanda y su subtotal.
     */
    private function buildOrder(): array
    {
        $weights = collect(DemoCatalog::dishes())->map(fn (array $dish) => $dish['weight'])->all();
        $lineCount = (int) $this->random->weighted([1 => 36, 2 => 35, 3 => 20, 4 => 9]);

        $items = [];
        $subtotal = 0.0;

        for ($i = 0; $i < $lineCount; $i++) {
            $name = (string) $this->random->weighted($weights);
            unset($weights[$name]);

            $quantity = (int) $this->random->weighted([1 => 80, 2 => 15, 3 => 5]);
            $item = ['dish_id' => $this->dishIds[$name], 'quantity' => $quantity];
            $subtotal += $this->dishPrices[$name] * $quantity;

            if (DemoCatalog::dishes()[$name]['with_sides'] && $this->random->chance(45)) {
                $names = array_slice($this->random->shuffle(array_keys($this->complementIds)), 0, $this->random->int(1, 2));

                foreach ($names as $complementName) {
                    $item['complements'][] = ['complement_id' => $this->complementIds[$complementName], 'quantity' => 1];
                    $subtotal += $this->complementPrices[$complementName];
                }
            }

            if ($this->random->chance(8)) {
                $item['notes'] = $this->random->pick(DemoCatalog::orderNotes());
            }

            $items[] = $item;
        }

        $dineIn = $this->random->chance(70);
        $payload = ['order_type' => $dineIn ? 'en_mesa' : 'para_llevar', 'items' => $items];

        if ($dineIn) {
            $payload['restaurant_table_id'] = $this->random->pick($this->tableIds);
        }

        return [$payload, round($subtotal, 2)];
    }

    private function payOrder(User $cashier, int $orderId, float $subtotal): void
    {
        $method = (string) $this->random->weighted(['efectivo' => 55, 'tarjeta' => 35, 'transferencia' => 10]);
        $discount = $this->random->chance(5) ? 5.0 : 0.0;
        $total = max(0.0, $subtotal - $discount);

        $payload = [
            'order_id' => $orderId,
            'payment_method' => $method,
            'receipt_type' => $this->random->chance(8) ? 'factura' : 'ticket',
        ];

        if ($discount > 0) {
            $payload['discount'] = $discount;
        }

        if ($method === 'efectivo') {
            $denomination = (int) $this->random->weighted([10 => 40, 20 => 20, 50 => 25, 100 => 15]);
            $payload['received_amount'] = max($total, ceil($total / $denomination) * $denomination);
        }

        $this->api->call($cashier, 'POST', '/sales', $payload);

        $this->stats['sales']++;
        $this->stats['revenue'] += $total;
    }

    /** Agrupa las alertas de stock bajo pendientes por proveedor, genera la compra y programa su recepción. */
    private function restockFromAlerts(CarbonImmutable $day): void
    {
        $alerts = SupplyAlert::query()
            ->whereNull('inventory_movement_id')
            ->whereHas('status', fn ($query) => $query->where('name', AlertStatus::PENDING))
            ->with('supply')
            ->orderBy('id')
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        $admin = $this->context->admin;
        $catalog = DemoCatalog::supplies();

        foreach ($alerts->groupBy(fn (SupplyAlert $alert) => $this->supplierBySupply[$alert->supply->code]) as $supplierId => $group) {
            $items = $group->map(function (SupplyAlert $alert) use ($catalog) {
                $definition = $catalog[$alert->supply->code];
                $missing = $definition['initial'] - (float) $alert->supply->current_stock;
                $quantity = in_array($definition['unit'], ['u', 'porc'], true) ? ceil($missing) : round($missing, 1);

                return ['supply_id' => $alert->supply_id, 'quantity' => max($definition['minimum'], $quantity), 'code' => $alert->supply->code];
            })->values();

            $this->api->call($admin, 'POST', '/purchase-requests', [
                'reason' => 'Reposición por alertas de stock bajo',
                'items' => $items->map(fn (array $item) => ['supply_id' => $item['supply_id'], 'suggested_quantity' => $item['quantity']])->all(),
            ]);
            $requestId = (int) PurchaseRequest::query()->latest('id')->value('id');

            foreach ($group as $alert) {
                $this->api->call($admin, 'POST', "/supply-alerts/{$alert->id}/attend", [
                    'purchase_request_id' => $requestId,
                    'notes' => 'Incluida en la solicitud de compra de reposición',
                ]);
            }

            $this->api->call($admin, 'POST', "/purchase-requests/{$requestId}/approve", [
                'supplier_id' => (int) $supplierId,
                'expected_date' => $day->toDateString(),
                'items' => $items->map(fn (array $item) => [
                    'supply_id' => $item['supply_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->agreedPrices[$item['code']],
                ])->all(),
            ]);

            $purchaseOrderId = (int) PurchaseOrder::query()->where('purchase_request_id', $requestId)->value('id');
            $this->stats['purchase_orders']++;

            if ($this->random->chance(12)) {
                // Entrega con problema: se reporta al llegar y la mercadería se recibe más tarde, ya corregida.
                $this->schedule($day->setTime(8, 10), fn () => $this->reportIncident($purchaseOrderId));
                $this->schedule($day->setTime(14, 5), fn () => $this->receive($purchaseOrderId, $day));
            } else {
                $this->schedule($day->setTime(8, 10), fn () => $this->receive($purchaseOrderId, $day));
            }
        }
    }

    private function reportIncident(int $purchaseOrderId): void
    {
        $typeIds = DeliveryIncidentType::query()->orderBy('id')->pluck('id')->all();

        $this->api->call($this->random->pick($this->context->waiters), 'POST', "/purchase-orders/{$purchaseOrderId}/incident", [
            'delivery_incident_type_id' => $this->random->pick($typeIds),
            'description' => $this->random->pick([
                'Llegaron menos unidades de las facturadas',
                'Parte de la mercadería llegó en mal estado',
                'La entrega llegó fuera del horario acordado',
            ]),
        ]);

        $this->stats['incidents']++;
    }

    private function receive(int $purchaseOrderId, CarbonImmutable $day): void
    {
        $receiver = $this->random->pick([$this->context->admin, ...$this->context->waiters]);

        $this->api->call($receiver, 'POST', "/purchase-orders/{$purchaseOrderId}/receive", [
            'received_date' => $day->toDateString(),
            'resolve_incidents' => true,
        ]);
    }

    private function registerWaste(): void
    {
        $code = $this->random->pick(['POL', 'PAP', 'REP', 'LIM', 'FLA', 'PAN']);
        $supply = Supply::query()->where('code', $code)->firstOrFail();
        $isCounted = in_array(DemoCatalog::supplies()[$code]['unit'], ['u', 'porc'], true);

        $this->api->call($this->random->pick($this->context->cooks), 'POST', '/inventory-movements', [
            'supply_id' => $supply->id,
            'type' => 'merma_dano',
            'quantity' => $isCounted ? $this->random->int(1, 4) : $this->random->int(5, 20) / 10,
            'reason' => $this->random->pick(DemoCatalog::wasteReasons()),
        ]);

        $this->stats['wastes']++;
    }

    /** Un cocinero solicita un ajuste por conteo físico y el administrador lo aprueba o lo rechaza. */
    private function requestAndReviewAdjustment(bool $approve): void
    {
        $supply = Supply::query()->where('code', $this->random->pick(['HAR', 'ARR', 'FRI']))->firstOrFail();
        $physical = max(0, round((float) $supply->current_stock - $this->random->int(5, 20) / 10, 2));

        $created = $this->api->call($this->random->pick($this->context->cooks), 'POST', '/inventory-movements', [
            'supply_id' => $supply->id,
            'type' => 'ajuste_inventario',
            'new_stock' => $physical,
            'reason' => 'Conteo físico: la existencia real difiere del sistema',
        ]);

        $movementId = (int) ($created['movement']['id'] ?? InventoryMovement::query()->latest('id')->value('id'));

        $this->at(CarbonImmutable::now()->addMinutes(10));
        $this->api->call($this->context->admin, 'POST', "/inventory-movements/{$movementId}/".($approve ? 'approve' : 'reject'), [
            'notes' => $approve ? 'Conteo verificado por el administrador' : 'La diferencia se debe a merma ya registrada',
        ]);

        $this->stats['adjustments']++;
    }

    /** Desplaza la fecha del sistema; nunca retrocede dentro de una misma simulación. */
    private function at(CarbonImmutable $moment): void
    {
        if ($this->lastMoment !== null && $moment < $this->lastMoment) {
            $moment = $this->lastMoment;
        }

        $this->lastMoment = $moment;
        $this->setNow($moment);
    }

    private function setNow(?CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }
}

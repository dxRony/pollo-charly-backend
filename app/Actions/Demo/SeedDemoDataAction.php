<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Models\Complement;
use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sale;
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
use RuntimeException;

/** Orquesta la carga de datos de demostración: usuarios, datos maestros e historial operativo. */
final class SeedDemoDataAction
{
    public function __construct(
        private readonly DemoApi $api,
        private readonly CreateDemoUsersAction $createUsers,
        private readonly SeedDemoCatalogAction $seedCatalog,
        private readonly SimulateDemoOperationsAction $simulateOperations,
    ) {}

    /**
     * @return array{credentials: array<int, array{name: string, email: string, role: string, password: string}>, stats: array<string, int|float>}
     */
    public function handle(int $days, int $seed, Closure $progress): array
    {
        $this->assertNoOperationalData();

        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMINISTRADOR))
            ->where('is_active', true)
            ->orderBy('id')
            ->first() ?? throw new RuntimeException('No existe un administrador activo. Ejecuta primero el ProductionSeeder.');

        // La carga no debe enviar correos ni emitir eventos a los clientes conectados.
        config(['mail.default' => 'array', 'broadcasting.default' => 'null']);

        $random = new DemoRandom($seed);
        $realNow = CarbonImmutable::now();
        $start = $realNow->setTimezone(DemoContext::TIMEZONE)->startOfDay()->subDays($days)->setTime(7, 0);

        try {
            $this->setNow($start);

            $created = $this->createUsers->handle();
            $byRole = fn (string $role) => array_values(array_map(
                fn (array $entry) => $entry['user'],
                array_filter($created, fn (array $entry) => $entry['user']->role?->name === $role),
            ));

            $context = new DemoContext($admin, $byRole(Role::MESERO_CAJERO), $byRole(Role::COCINERO));

            $this->seedCatalog->handle($context, $progress);
            $this->seedCatalog->publishDailyMenu($context);

            $stats = $this->simulateOperations->handle($context, $days, $random, $realNow, $progress);

            $this->setNow(null);
            $this->simulateOperations->leaveShowcasePendings($context, $random);
            $this->seedCatalog->publishDailyMenu($context);
        } finally {
            $this->setNow(null);
            $this->api->revokeTokens();
        }

        return [
            'credentials' => array_map(fn (array $entry) => [
                'name' => $entry['user']->name,
                'email' => $entry['user']->email,
                'role' => (string) $entry['user']->role?->name,
                'password' => $entry['password'],
            ], $created),
            'stats' => $stats + [
                'supplies' => Supply::query()->count(),
                'dishes' => Dish::query()->count(),
                'inventory_movements' => InventoryMovement::query()->count(),
                'low_stock_alerts' => SupplyAlert::query()->count(),
                'purchase_orders_total' => PurchaseOrder::query()->count(),
            ],
        ];
    }

    /** Evita cargar la demostración sobre una base que ya tiene datos operativos (o que ya la recibió). */
    private function assertNoOperationalData(): void
    {
        $existing = collect([
            'insumos' => Supply::query()->count(),
            'platillos' => Dish::query()->count(),
            'complementos' => Complement::query()->count(),
            'proveedores' => Supplier::query()->count(),
            'comandas' => Order::query()->count(),
            'ventas' => Sale::query()->count(),
        ])->filter();

        if ($existing->isNotEmpty()) {
            throw new RuntimeException(
                'La base de datos ya contiene datos ('.$existing->map(fn (int $count, string $name) => "{$count} {$name}")->implode(', ').'). '
                .'Para cargar la demostración, primero hay que reiniciarla con scripts/reset-database.sh.'
            );
        }

        $demoEmails = collect(DemoCatalog::users())->pluck('email');

        if (User::query()->whereIn('email', $demoEmails)->exists()) {
            throw new RuntimeException('Ya existen usuarios de demostración. Reinicia la base de datos antes de volver a cargarla.');
        }
    }

    private function setNow(?CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }
}

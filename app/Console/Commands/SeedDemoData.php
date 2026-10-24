<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\SeedDemoDataAction;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use RuntimeException;

class SeedDemoData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:seed
                            {--days=30 : Días de historial a simular (de 1 a 90)}
                            {--seed=2026 : Semilla del generador; la misma semilla produce los mismos datos}
                            {--force : Ejecutar en producción sin pedir confirmación}';

    protected $description = 'Carga datos ficticios de demostración (catálogo, ventas, compras e inventario) usando las reglas reales del sistema';

    public function handle(SeedDemoDataAction $seedDemoData): int
    {
        $days = (int) $this->option('days');

        if ($days < 1 || $days > 90) {
            $this->error('--days debe estar entre 1 y 90.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->info('Cargando datos de demostración (puede tardar unos minutos)...');

        try {
            $result = $seedDemoData->handle($days, (int) $this->option('seed'), fn (string $line) => $this->line("  · {$line}"));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $stats = $result['stats'];

        $this->newLine();
        $this->info('Resumen');
        $this->table(['Concepto', 'Cantidad'], [
            ['Comandas registradas', $stats['orders']],
            ['  canceladas', $stats['cancelled']],
            ['  sin stock (no registradas)', $stats['out_of_stock']],
            ['Ventas cobradas', $stats['sales']],
            ['Ingresos simulados (Q)', number_format((float) $stats['revenue'], 2)],
            ['Órdenes de compra', $stats['purchase_orders_total']],
            ['Entregas con incidencia', $stats['incidents']],
            ['Mermas', $stats['wastes']],
            ['Ajustes de inventario revisados', $stats['adjustments']],
            ['Movimientos de inventario', $stats['inventory_movements']],
            ['Alertas de stock bajo', $stats['low_stock_alerts']],
        ]);

        $this->newLine();
        $this->warn('Usuarios de demostración. ANOTA ESTAS CONTRASEÑAS: no se guardan en ningún otro lugar.');
        $this->table(['Nombre', 'Correo', 'Rol', 'Contraseña'], array_map(fn (array $row) => array_values($row), $result['credentials']));

        return self::SUCCESS;
    }
}

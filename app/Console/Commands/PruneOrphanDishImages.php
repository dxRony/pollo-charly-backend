<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dishes\PruneOrphanDishImagesAction;
use Illuminate\Console\Command;

class PruneOrphanDishImages extends Command
{
    protected $signature = 'images:prune-orphans
                            {--hours=24 : Antigüedad mínima de la imagen para considerarla huérfana}
                            {--dry-run : Solo muestra cuántas se eliminarían, sin borrar nada}';

    protected $description = 'Elimina de Cloudinary las imágenes de platillos que ningún platillo usa';

    public function handle(PruneOrphanDishImagesAction $pruneOrphanImages): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $pruneOrphanImages->handle((int) $this->option('hours'), $dryRun);

        $this->table(
            ['Imágenes en la carpeta', 'Huérfanas', 'Eliminadas', 'Conservadas por ser recientes'],
            [[$result['found'], $result['orphans'], $result['deleted'], $result['kept_recent']]],
        );

        if ($dryRun) {
            $this->info('Simulación: no se eliminó nada.');
        }

        return self::SUCCESS;
    }
}

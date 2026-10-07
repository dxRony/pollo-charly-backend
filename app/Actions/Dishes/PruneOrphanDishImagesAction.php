<?php

declare(strict_types=1);

namespace App\Actions\Dishes;

use App\Models\Dish;
use App\Services\Images\ImageStorage;
use Carbon\CarbonImmutable;

final class PruneOrphanDishImagesAction
{
    public function __construct(private readonly ImageStorage $imageStorage) {}

    /**
     * Elimina las imágenes de la carpeta de platillos que ningún platillo referencia.
     *
     * Las imágenes más recientes que $minAgeHours se conservan: pueden pertenecer a un formulario
     * que alguien está llenando y todavía no guardó.
     *
     * @return array{found: int, orphans: int, deleted: int, kept_recent: int}
     */
    public function handle(int $minAgeHours, bool $dryRun): array
    {
        $inUse = array_flip(Dish::query()->whereNotNull('image_url')->pluck('image_url')->all());
        $limit = CarbonImmutable::now()->subHours($minAgeHours);

        $result = ['found' => 0, 'orphans' => 0, 'deleted' => 0, 'kept_recent' => 0];

        foreach ($this->imageStorage->list(Dish::imageFolder()) as $image) {
            $result['found']++;

            if (isset($inUse[$image['url']])) {
                continue;
            }

            if ($image['created_at']->greaterThan($limit)) {
                $result['kept_recent']++;

                continue;
            }

            $result['orphans']++;

            if (! $dryRun && $this->imageStorage->delete($image['url'])) {
                $result['deleted']++;
            }
        }

        return $result;
    }
}

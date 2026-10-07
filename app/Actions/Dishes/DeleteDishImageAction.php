<?php

declare(strict_types=1);

namespace App\Actions\Dishes;

use App\Models\Dish;
use App\Services\Images\ImageStorage;
use Throwable;

final class DeleteDishImageAction
{
    public function __construct(private readonly ImageStorage $imageStorage) {}

    /**
     * Elimina una imagen de platillo del servicio de almacenamiento si ningún platillo la usa.
     *
     * Es una limpieza de mejor esfuerzo: un fallo del servicio externo no debe deshacer la operación
     * del usuario, así que se reporta y se devuelve false. Solo se tocan imágenes de la carpeta de este
     * entorno: los enlaces externos antiguos y las imágenes de otro entorno se ignoran.
     *
     * @return bool true si la imagen se eliminó.
     */
    public function handle(string $imageUrl): bool
    {
        if (! str_contains($imageUrl, '/'.Dish::imageFolder().'/')) {
            return false;
        }

        if (Dish::query()->where('image_url', $imageUrl)->exists()) {
            return false;
        }

        try {
            return $this->imageStorage->delete($imageUrl);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}

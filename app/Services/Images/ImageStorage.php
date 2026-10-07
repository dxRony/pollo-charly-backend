<?php

declare(strict_types=1);

namespace App\Services\Images;

use App\Exceptions\ImageStorageNotConfiguredException;
use App\Exceptions\ImageUploadFailedException;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

interface ImageStorage
{
    /**
     * Sube la imagen al servicio de almacenamiento y devuelve su URL pública (HTTPS).
     *
     * @throws ImageStorageNotConfiguredException Si el servicio no está configurado.
     * @throws ImageUploadFailedException Si el servicio rechazó o no pudo recibir la imagen.
     */
    public function upload(UploadedFile $file, string $folder): string;

    /**
     * Elimina la imagen identificada por su URL. Solo actúa sobre imágenes de este servicio
     * y de esta cuenta; cualquier otra URL (por ejemplo un enlace externo) se ignora.
     *
     * @return bool true si la imagen existía y se eliminó.
     *
     * @throws ImageStorageNotConfiguredException Si el servicio no está configurado.
     */
    public function delete(string $url): bool;

    /**
     * Lista las imágenes almacenadas dentro de una carpeta.
     *
     * @return iterable<int, array{url: string, created_at: CarbonImmutable}>
     *
     * @throws ImageStorageNotConfiguredException Si el servicio no está configurado.
     */
    public function list(string $folder): iterable;
}

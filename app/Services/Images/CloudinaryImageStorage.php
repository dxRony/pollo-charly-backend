<?php

declare(strict_types=1);

namespace App\Services\Images;

use App\Exceptions\ImageStorageNotConfiguredException;
use App\Exceptions\ImageUploadFailedException;
use Carbon\CarbonImmutable;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Throwable;

final class CloudinaryImageStorage implements ImageStorage
{
    /** Lado máximo en píxeles: suficiente para una tarjeta de menú y evita guardar fotos de 12 MP. */
    private const MAX_SIDE_PIXELS = 1000;

    private const PAGE_SIZE = 100;

    public function upload(UploadedFile $file, string $folder): string
    {
        try {
            $response = $this->client()->uploadApi()->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'image',
                'transformation' => [
                    'width' => self::MAX_SIDE_PIXELS,
                    'height' => self::MAX_SIDE_PIXELS,
                    'crop' => 'limit',
                    'quality' => 'auto',
                ],
            ]);
        } catch (ImageStorageNotConfiguredException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ImageUploadFailedException(previous: $exception);
        }

        return (string) $response['secure_url'];
    }

    public function delete(string $url): bool
    {
        $client = $this->client();
        $publicId = $this->publicIdFromUrl($url, (string) $client->configuration->cloud->cloudName);

        if ($publicId === null) {
            return false;
        }

        $response = $client->uploadApi()->destroy($publicId, ['resource_type' => 'image', 'invalidate' => true]);

        return ($response['result'] ?? null) === 'ok';
    }

    public function list(string $folder): iterable
    {
        $client = $this->client();
        $cursor = null;

        do {
            $page = $client->adminApi()->assets(array_filter([
                'type' => 'upload',
                'prefix' => rtrim($folder, '/').'/',
                'max_results' => self::PAGE_SIZE,
                'next_cursor' => $cursor,
            ]));

            foreach ($page['resources'] ?? [] as $resource) {
                yield [
                    'url' => (string) $resource['secure_url'],
                    'created_at' => CarbonImmutable::parse($resource['created_at']),
                ];
            }

            $cursor = $page['next_cursor'] ?? null;
        } while ($cursor !== null);
    }

    private function client(): Cloudinary
    {
        $url = (string) config('services.cloudinary.url');

        if (! str_starts_with($url, 'cloudinary://')) {
            throw new ImageStorageNotConfiguredException;
        }

        return new Cloudinary($url);
    }

    /**
     * Extrae el public_id de una URL de entrega (https://res.cloudinary.com/<cuenta>/image/upload/v123/carpeta/id.jpg).
     * Devuelve null si la URL no pertenece a la cuenta configurada.
     */
    private function publicIdFromUrl(string $url, string $cloudName): ?string
    {
        $pattern = '~^https://res\.cloudinary\.com/'.preg_quote($cloudName, '~').'/image/upload/(?:v\d+/)?(.+)\.[A-Za-z0-9]+$~';

        return preg_match($pattern, $url, $matches) === 1 ? $matches[1] : null;
    }
}

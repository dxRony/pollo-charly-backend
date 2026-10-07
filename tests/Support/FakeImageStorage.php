<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Dish;
use App\Services\Images\ImageStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Servicio de imágenes en memoria para las pruebas: evita depender de Cloudinary e internet.
 */
final class FakeImageStorage implements ImageStorage
{
    /** @var array<string, CarbonImmutable> Imágenes almacenadas: URL => fecha de creación. */
    public array $stored = [];

    /** @var array<int, array{nombre: string, carpeta: string, url: string}> */
    public array $uploads = [];

    /** @var array<int, string> URL eliminadas con éxito. */
    public array $deleted = [];

    public ?Throwable $uploadFailure = null;

    public ?Throwable $deleteFailure = null;

    /** URL falsa de una imagen dentro de la carpeta de platillos del entorno (o de otra carpeta indicada). */
    public static function url(string $nombre, ?string $carpeta = null): string
    {
        return 'https://res.cloudinary.test/'.($carpeta ?? Dish::imageFolder()).'/'.$nombre.'.jpg';
    }

    public static function install(): self
    {
        $fake = new self;
        app()->instance(ImageStorage::class, $fake);

        return $fake;
    }

    public function upload(UploadedFile $file, string $folder): string
    {
        if ($this->uploadFailure) {
            throw $this->uploadFailure;
        }

        $url = sprintf('https://res.cloudinary.test/%s/foto-%d.jpg', $folder, count($this->uploads) + 1);

        $this->uploads[] = ['nombre' => $file->getClientOriginalName(), 'carpeta' => $folder, 'url' => $url];
        $this->stored[$url] = CarbonImmutable::now();

        return $url;
    }

    public function delete(string $url): bool
    {
        if ($this->deleteFailure) {
            throw $this->deleteFailure;
        }

        if (! isset($this->stored[$url])) {
            return false;
        }

        unset($this->stored[$url]);
        $this->deleted[] = $url;

        return true;
    }

    public function list(string $folder): iterable
    {
        foreach ($this->stored as $url => $createdAt) {
            if (str_contains($url, '/'.$folder.'/')) {
                yield ['url' => $url, 'created_at' => $createdAt];
            }
        }
    }

    /** Registra una imagen que ya existía en el servicio, con la antigüedad indicada. */
    public function seed(string $url, int $hoursOld = 0): string
    {
        $this->stored[$url] = CarbonImmutable::now()->subHours($hoursOld);

        return $url;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Dishes;

use App\Models\Dish;
use App\Services\Images\ImageStorage;
use Illuminate\Http\UploadedFile;

final class UploadDishImageAction
{
    public function __construct(private readonly ImageStorage $imageStorage) {}

    /**
     * @return string URL pública de la imagen, lista para guardarse en dishes.image_url.
     */
    public function handle(UploadedFile $image): string
    {
        return $this->imageStorage->upload($image, Dish::imageFolder());
    }
}

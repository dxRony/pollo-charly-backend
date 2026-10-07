<?php

declare(strict_types=1);

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UploadDishImageRequest',
    title: 'Upload Dish Image Request',
    description: 'Imagen del platillo enviada como multipart/form-data',
    required: ['image'],
    properties: [
        new OA\Property(
            property: 'image',
            description: 'Archivo de imagen JPG, PNG o WebP de máximo 2 MB',
            type: 'string',
            format: 'binary'
        ),
    ]
)]
class UploadDishImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Selecciona una imagen para el platillo.',
            'image.file' => 'El archivo seleccionado no es válido.',
            'image.uploaded' => 'No se pudo cargar la imagen. Verifica que no supere los 2 MB.',
            'image.mimes' => 'La imagen debe ser un archivo JPG, PNG o WebP.',
            'image.max' => 'La imagen no puede superar los 2 MB.',
        ];
    }
}

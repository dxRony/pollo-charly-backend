<?php

declare(strict_types=1);

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DiscardDishImageRequest',
    title: 'Discard Dish Image Request',
    description: 'URL de una imagen subida que no se llegó a asociar a ningún platillo',
    required: ['image_url'],
    properties: [
        new OA\Property(
            property: 'image_url',
            description: 'URL devuelta por el endpoint de subida',
            type: 'string',
            example: 'https://res.cloudinary.com/demo/image/upload/v1/pollo-charly/dishes/abc123.jpg'
        ),
    ]
)]
class DiscardDishImageRequest extends FormRequest
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
            'image_url' => ['required', 'string', 'url:https', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image_url.required' => 'La URL de la imagen es obligatoria.',
            'image_url.url' => 'La URL de la imagen no es válida.',
            'image_url.max' => 'La URL de la imagen es demasiado larga.',
        ];
    }
}

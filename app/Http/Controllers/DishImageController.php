<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dishes\DeleteDishImageAction;
use App\Actions\Dishes\UploadDishImageAction;
use App\Http\Requests\Dish\DiscardDishImageRequest;
use App\Http\Requests\Dish\UploadDishImageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class DishImageController extends Controller
{
    #[OA\Post(
        path: '/api/uploads/dish-image',
        operationId: 'uploadDishImage',
        description: 'Sube la imagen de un platillo al servicio de almacenamiento (Cloudinary) y devuelve su URL pública, que luego se envía en el campo image_url al crear o editar el platillo.',
        summary: 'Subir la imagen de un platillo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/UploadDishImageRequest')
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Imagen almacenada correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Imagen cargada correctamente.'),
                        new OA\Property(property: 'image_url', type: 'string', example: 'https://res.cloudinary.com/demo/image/upload/v1/pollo-charly/dishes/abc123.jpg'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Archivo ausente, de un formato no permitido o mayor a 2 MB.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La imagen debe ser un archivo JPG, PNG o WebP.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acceso denegado (solo Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
            new OA\Response(
                response: 502,
                description: 'El servicio de imágenes rechazó la carga o no respondió.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No se pudo guardar la imagen. Intenta de nuevo en unos minutos.')]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'El servicio de imágenes no está configurado en el servidor.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'El servicio de almacenamiento de imágenes no está configurado.')]
                )
            ),
        ]
    )]
    public function store(UploadDishImageRequest $request, UploadDishImageAction $uploadDishImage): JsonResponse
    {
        $imageUrl = $uploadDishImage->handle($request->file('image'));

        return response()->json([
            'message' => 'Imagen cargada correctamente.',
            'image_url' => $imageUrl,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/uploads/dish-image',
        operationId: 'discardDishImage',
        description: 'Elimina del servicio de almacenamiento una imagen que se subió pero no se asoció a ningún platillo (por ejemplo, al cancelar el formulario o reemplazarla antes de guardar). Es idempotente: si la imagen la usa un platillo, no es de este servicio o ya no existe, no se elimina nada y se responde igualmente 204.',
        summary: 'Descartar una imagen subida y no utilizada',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DiscardDishImageRequest')
        ),
        responses: [
            new OA\Response(response: 204, description: 'Solicitud procesada.'),
            new OA\Response(
                response: 422,
                description: 'URL ausente o inválida.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La URL de la imagen es obligatoria.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acceso denegado (solo Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
        ]
    )]
    public function destroy(DiscardDishImageRequest $request, DeleteDishImageAction $deleteDishImage): Response
    {
        $deleteDishImage->handle($request->validated('image_url'));

        return response()->noContent();
    }
}

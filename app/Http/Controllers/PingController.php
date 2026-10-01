<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PingController extends Controller
{
    #[OA\Get(
        path: '/api/ping',
        operationId: 'ping',
        description: 'Endpoint de diagnóstico para comprobar la disponibilidad y conectividad con la API.',
        summary: 'Verificar estado de la API',
        tags: ['General'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'La API está en línea y respondiendo.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'pong desde Laravel'
                        ),
                        new OA\Property(
                            property: 'timestamp',
                            type: 'string',
                            example: '2026-09-20 18:00:00'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'message' => 'pong desde Laravel',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

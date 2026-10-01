<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserRole',
    title: 'User Role',
    description: 'Rol del usuario dentro del sistema',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Administrador'),
    ]
)]
#[OA\Schema(
    schema: 'UserResource',
    title: 'User Resource',
    description: 'Datos del usuario',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Ana Administradora'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@pollocharly.com'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'two_factor_enabled', type: 'boolean', example: false),
        new OA\Property(property: 'role', ref: '#/components/schemas/UserRole', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

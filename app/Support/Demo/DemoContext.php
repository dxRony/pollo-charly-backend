<?php

declare(strict_types=1);

namespace App\Support\Demo;

use App\Models\User;

/** Usuarios que intervienen en la simulación y zona horaria del restaurante. */
final class DemoContext
{
    public const TIMEZONE = 'America/Guatemala';

    /**
     * @param  array<int, User>  $waiters  Meseros/cajeros.
     * @param  array<int, User>  $cooks  Cocineros.
     */
    public function __construct(
        public readonly User $admin,
        public readonly array $waiters,
        public readonly array $cooks,
    ) {}
}

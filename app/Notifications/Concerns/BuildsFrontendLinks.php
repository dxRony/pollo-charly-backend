<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

trait BuildsFrontendLinks
{
    /**
     * Dirección absoluta de una pantalla del frontend (p. ej. "/login").
     */
    protected function frontendUrl(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}

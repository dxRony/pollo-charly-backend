<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ImageUploadFailedException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('No se pudo guardar la imagen. Intenta de nuevo en unos minutos.', 0, $previous);
    }
}

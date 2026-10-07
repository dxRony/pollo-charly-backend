<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ImageStorageNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El servicio de almacenamiento de imágenes no está configurado.');
    }
}

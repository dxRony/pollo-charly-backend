<?php

declare(strict_types=1);

namespace App\Actions\Users;

final class GenerateTemporaryPasswordAction
{
    /** Sin caracteres ambiguos (0/O, 1/l/I) para que sea fácil de leer y copiar desde el correo. */
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 12;

    public function handle(): string
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $password;
    }
}

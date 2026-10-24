<?php

declare(strict_types=1);

namespace App\Support\Demo;

use RuntimeException;

final class DemoApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly int $status,
        public readonly array $body,
    ) {
        parent::__construct(sprintf(
            '%s %s respondió %d: %s',
            $method,
            $uri,
            $status,
            (string) ($body['message'] ?? json_encode($body, JSON_UNESCAPED_UNICODE)),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class ServiceUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $service,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "Failed to connect to {$service}. Check availability and try again later.",
            503,
            $previous,
        );
    }
}

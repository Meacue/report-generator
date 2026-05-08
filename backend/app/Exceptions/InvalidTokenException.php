<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class InvalidTokenException extends RuntimeException
{
    public function __construct(
        public readonly string $service,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "{$service} token is invalid or expired. Update it in settings.",
            401,
            $previous,
        );
    }
}

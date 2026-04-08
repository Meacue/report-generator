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
            "Токен {$service} невалиден или истёк. Обновите его в настройках.",
            401,
            $previous,
        );
    }
}

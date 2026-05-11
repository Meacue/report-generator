<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class NoDataException extends RuntimeException
{
    public function __construct(string $message = 'No data available to generate the report. Run a sync or check your settings.')
    {
        parent::__construct($message, 422);
    }
}

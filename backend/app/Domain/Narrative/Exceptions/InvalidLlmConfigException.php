<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Exceptions;

use RuntimeException;

final class InvalidLlmConfigException extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('LLM configuration is invalid: ' . implode('; ', $violations), 422);
    }
}

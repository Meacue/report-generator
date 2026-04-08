<?php

declare(strict_types=1);

namespace App\Services\LLM;

use InvalidArgumentException;

class LlmManager
{
    /**
     * @param  array<string, LlmProviderInterface>  $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly string $defaultProvider,
    ) {
    }

    public function provider(?string $name = null): LlmProviderInterface
    {
        $name ??= $this->defaultProvider;

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("LLM provider [{$name}] is not configured.");
        }

        return $this->providers[$name];
    }
}

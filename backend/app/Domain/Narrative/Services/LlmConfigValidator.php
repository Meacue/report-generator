<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Services;

use App\Domain\Narrative\Exceptions\InvalidLlmConfigException;

final readonly class LlmConfigValidator
{
    public function __construct(private LlmProviderInterface $provider)
    {
    }

    /**
     * @throws InvalidLlmConfigException
     */
    public function validate(): void
    {
        $violations = $this->provider->validate();

        if ($violations !== []) {
            throw new InvalidLlmConfigException($violations);
        }
    }
}

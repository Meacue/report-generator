<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class TaskNumber
{
    public string $value;

    public function __construct(string|int $value)
    {
        $this->value = (string) $value;

        if (trim($this->value) === '') {
            throw new InvalidArgumentException('Task number cannot be empty');
        }
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

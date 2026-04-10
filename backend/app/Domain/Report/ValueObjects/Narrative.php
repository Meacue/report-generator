<?php

declare(strict_types=1);

namespace App\Domain\Report\ValueObjects;

final readonly class Narrative
{
    public function __construct(
        public string $text,
    ) {
    }

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }

    public function isPlaceholder(): bool
    {
        return str_contains($this->text, '[Не удалось сгенерировать описание');
    }

    public function equals(self $other): bool
    {
        return $this->text === $other->text;
    }

    public function __toString(): string
    {
        return $this->text;
    }

    public static function placeholder(): self
    {
        return new self('[Не удалось сгенерировать описание. Отредактируйте вручную.]');
    }
}

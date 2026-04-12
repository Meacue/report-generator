<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

final readonly class DateRange
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(string|CarbonImmutable $from, string|CarbonImmutable $to)
    {
        $this->from = CarbonImmutable::parse($from);
        $this->to = CarbonImmutable::parse($to);

        if ($this->from->isAfter($this->to)) {
            throw new InvalidArgumentException(
                "Date 'from' ({$this->from->toDateString()}) must be before or equal to 'to' ({$this->to->toDateString()})"
            );
        }
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->between($this->from, $this->to);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function toPeriod(): CarbonPeriod
    {
        return CarbonPeriod::create($this->from, $this->to);
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->from->toDateString(),
            'date_to'   => $this->to->toDateString(),
        ];
    }
}

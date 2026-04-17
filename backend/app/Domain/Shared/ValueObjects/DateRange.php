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
        $this->from = (CarbonImmutable::parse($from))->utc();
        $this->to = (CarbonImmutable::parse($to))->utc();

        if ($this->from->isAfter($this->to)) {
            throw new InvalidArgumentException(
                "Date 'from' ({$this->from->toDateString()}) must be before or equal to 'to' ({$this->to->toDateString()})"
            );
        }
    }

    /**
     * Create a range covering the last N days up to now (inclusive).
     *
     * Both boundaries are normalised to UTC midnight so the range is
     * always an integer number of calendar days.
     */
    public static function lastDays(int $days): self
    {
        return new self(
            CarbonImmutable::now('UTC')->subDays($days)->startOfDay(),
            CarbonImmutable::now('UTC')->endOfDay(),
        );
    }

    /**
     * Create a range from two explicit CarbonImmutable boundaries.
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from, $to);
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->between($this->from, $this->to);
    }

    /**
     * Number of calendar days covered by the range (inclusive on both ends).
     */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * Whether the range exceeds the given maximum number of days.
     * Useful for enforcing API rate-limit windows (e.g. 30-day cap).
     */
    public function exceeds(int $maxDays): bool
    {
        return $this->days() > $maxDays;
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

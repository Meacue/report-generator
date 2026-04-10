<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\DateRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function test_creates_from_strings(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-31');

        $this->assertSame('2024-01-01', $range->from->toDateString());
        $this->assertSame('2024-01-31', $range->to->toDateString());
    }

    public function test_creates_from_carbon_immutable(): void
    {
        $from = CarbonImmutable::parse('2024-03-01');
        $to = CarbonImmutable::parse('2024-03-31');
        $range = new DateRange($from, $to);

        $this->assertTrue($from->equalTo($range->from));
        $this->assertTrue($to->equalTo($range->to));
    }

    public function test_throws_when_from_after_to(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DateRange('2024-02-01', '2024-01-01');
    }

    public function test_contains_date_inside_range(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-31');

        $this->assertTrue($range->contains(CarbonImmutable::parse('2024-01-15')));
    }

    public function test_does_not_contain_date_outside_range(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-31');

        $this->assertFalse($range->contains(CarbonImmutable::parse('2024-02-01')));
    }

    public function test_contains_boundary_dates(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-31');

        $this->assertTrue($range->contains(CarbonImmutable::parse('2024-01-01')));
        $this->assertTrue($range->contains(CarbonImmutable::parse('2024-01-31')));
    }

    public function test_days_count(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-07');

        $this->assertSame(7, $range->days());
    }

    public function test_single_day_range_has_one_day(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-01');

        $this->assertSame(1, $range->days());
    }

    public function test_to_period_iterates_correct_number_of_dates(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-03');

        $this->assertCount(3, iterator_to_array($range->toPeriod()));
    }

    public function test_to_array_returns_correct_keys_and_values(): void
    {
        $range = new DateRange('2024-01-01', '2024-01-31');

        $this->assertSame([
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ], $range->toArray());
    }

    public function test_equal_from_and_to_does_not_throw(): void
    {
        $range = new DateRange('2024-06-15', '2024-06-15');

        $this->assertSame('2024-06-15', $range->from->toDateString());
        $this->assertSame('2024-06-15', $range->to->toDateString());
    }
}

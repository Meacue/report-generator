<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\TaskNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaskNumberTest extends TestCase
{
    public function test_creates_with_string(): void
    {
        $taskNumber = new TaskNumber('TASK-123');

        $this->assertSame('TASK-123', $taskNumber->value);
    }

    public function test_creates_with_int(): void
    {
        $taskNumber = new TaskNumber(456);

        $this->assertSame('456', $taskNumber->value);
    }

    public function test_throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaskNumber('');
    }

    public function test_throws_on_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaskNumber('   ');
    }

    public function test_to_int_returns_integer_value(): void
    {
        $taskNumber = new TaskNumber('123');

        $this->assertSame(123, $taskNumber->toInt());
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $a = new TaskNumber('123');
        $b = new TaskNumber('123');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $a = new TaskNumber('123');
        $c = new TaskNumber('456');

        $this->assertFalse($a->equals($c));
    }

    public function test_to_string_returns_value(): void
    {
        $taskNumber = new TaskNumber('123');

        $this->assertSame('123', (string) $taskNumber);
    }

    public function test_int_and_string_representations_are_equal(): void
    {
        $fromInt = new TaskNumber(99);
        $fromString = new TaskNumber('99');

        $this->assertTrue($fromInt->equals($fromString));
    }
}

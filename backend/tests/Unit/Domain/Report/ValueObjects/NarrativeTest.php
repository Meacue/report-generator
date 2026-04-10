<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\ValueObjects;

use App\Domain\Report\ValueObjects\Narrative;
use PHPUnit\Framework\TestCase;

final class NarrativeTest extends TestCase
{
    public function test_is_empty_for_blank_string(): void
    {
        $this->assertTrue((new Narrative(''))->isEmpty());
    }

    public function test_is_empty_for_whitespace_only(): void
    {
        $this->assertTrue((new Narrative('   '))->isEmpty());
    }

    public function test_is_not_empty_for_text(): void
    {
        $this->assertFalse((new Narrative('Some work done'))->isEmpty());
    }

    public function test_placeholder_is_recognized(): void
    {
        $placeholder = Narrative::placeholder();

        $this->assertTrue($placeholder->isPlaceholder());
    }

    public function test_regular_text_is_not_placeholder(): void
    {
        $this->assertFalse((new Narrative('Real narrative'))->isPlaceholder());
    }

    public function test_equals_returns_true_for_same_text(): void
    {
        $a = new Narrative('text');
        $b = new Narrative('text');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_text(): void
    {
        $a = new Narrative('text');
        $c = new Narrative('other');

        $this->assertFalse($a->equals($c));
    }

    public function test_to_string_returns_text(): void
    {
        $narrative = new Narrative('hello');

        $this->assertSame('hello', (string) $narrative);
    }

    public function test_placeholder_is_not_empty(): void
    {
        $placeholder = Narrative::placeholder();

        $this->assertFalse($placeholder->isEmpty());
    }

    public function test_placeholder_text_is_stored_verbatim(): void
    {
        $placeholder = Narrative::placeholder();

        $this->assertStringContainsString('[Не удалось сгенерировать описание', (string) $placeholder);
    }
}

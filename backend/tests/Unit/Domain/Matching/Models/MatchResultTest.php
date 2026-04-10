<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Matching\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\ResolvedBy;
use App\Models\Branch;
use App\Models\MatchResult;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MatchResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_manual_match_sets_confidence_auto(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $match = MatchResult::createManualMatch($branch->id, $task->id);

        $this->assertSame(ConfidenceLevel::Auto, $match->confidence_level);
    }

    public function test_create_manual_match_sets_resolved_by_user(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $match = MatchResult::createManualMatch($branch->id, $task->id);

        $this->assertSame(ResolvedBy::User, $match->resolved_by);
    }

    public function test_create_manual_match_sets_resolved_at(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $match = MatchResult::createManualMatch($branch->id, $task->id);

        $this->assertNotNull($match->resolved_at);
    }

    public function test_create_manual_match_links_correct_task(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $match = MatchResult::createManualMatch($branch->id, $task->id);

        $this->assertSame($task->id, $match->task_id);
    }

    public function test_create_ignored_has_no_task(): void
    {
        $branch = Branch::factory()->create();

        $match = MatchResult::createIgnored($branch->id);

        $this->assertNull($match->task_id);
    }

    public function test_create_ignored_sets_confidence_none(): void
    {
        $branch = Branch::factory()->create();

        $match = MatchResult::createIgnored($branch->id);

        $this->assertSame(ConfidenceLevel::None, $match->confidence_level);
    }

    public function test_create_ignored_sets_resolved_by_user(): void
    {
        $branch = Branch::factory()->create();

        $match = MatchResult::createIgnored($branch->id);

        $this->assertSame(ResolvedBy::User, $match->resolved_by);
    }

    public function test_is_confirmed_by_user_returns_true_when_resolved_by_user(): void
    {
        $match = MatchResult::factory()->create(['resolved_by' => ResolvedBy::User]);

        $this->assertTrue($match->isConfirmedByUser());
    }

    public function test_is_confirmed_by_user_returns_false_when_resolved_by_system(): void
    {
        $match = MatchResult::factory()->create(['resolved_by' => ResolvedBy::System]);

        $this->assertFalse($match->isConfirmedByUser());
    }

    public function test_is_auto_matched_returns_true_for_auto_system_match(): void
    {
        $match = MatchResult::factory()->create([
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::System,
        ]);

        $this->assertTrue($match->isAutoMatched());
    }

    public function test_is_auto_matched_returns_false_for_user_resolved_match(): void
    {
        $match = MatchResult::factory()->create([
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::User,
        ]);

        $this->assertFalse($match->isAutoMatched());
    }

    public function test_is_ignored_returns_true_when_no_task_and_confidence_none(): void
    {
        $match = MatchResult::factory()->create([
            'task_id'          => null,
            'confidence_level' => ConfidenceLevel::None,
        ]);

        $this->assertTrue($match->isIgnored());
    }

    public function test_is_ignored_returns_false_when_task_is_set(): void
    {
        $task = Task::factory()->create();
        $match = MatchResult::factory()->create([
            'task_id'          => $task->id,
            'confidence_level' => ConfidenceLevel::Auto,
        ]);

        $this->assertFalse($match->isIgnored());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Matching\Models;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\MatchResult;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_matched_returns_true_when_user_resolved_match_with_task_exists(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();
        MatchResult::factory()->create([
            'branch_id'   => $branch->id,
            'task_id'     => $task->id,
            'resolved_by' => ResolvedBy::User,
        ]);

        $this->assertTrue($branch->isMatched());
    }

    public function test_is_matched_returns_false_when_no_match_results(): void
    {
        $branch = Branch::factory()->create();

        $this->assertFalse($branch->isMatched());
    }

    public function test_is_matched_returns_false_when_only_system_match_exists(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();
        MatchResult::factory()->create([
            'branch_id'   => $branch->id,
            'task_id'     => $task->id,
            'resolved_by' => ResolvedBy::System,
        ]);

        $this->assertFalse($branch->isMatched());
    }

    public function test_has_task_number_returns_true_when_parsed_task_number_is_set(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => 12345]);

        $this->assertTrue($branch->hasTaskNumber());
    }

    public function test_has_task_number_returns_false_when_parsed_task_number_is_null(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => null]);

        $this->assertFalse($branch->hasTaskNumber());
    }

    public function test_get_commits_in_period_returns_commits_within_range(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2024-01-15 10:00:00',
        ]);

        $range = new DateRange('2024-01-01', '2024-01-31');
        $commits = $branch->getCommitsInPeriod($range);

        $this->assertCount(1, $commits);
    }

    public function test_get_commits_in_period_excludes_commits_outside_range(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2024-03-01 10:00:00',
        ]);

        $range = new DateRange('2024-01-01', '2024-01-31');
        $commits = $branch->getCommitsInPeriod($range);

        $this->assertCount(0, $commits);
    }

    public function test_get_commits_in_period_includes_boundary_dates(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2024-01-01 00:00:00',
        ]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2024-01-31 00:00:00',
        ]);

        $range = new DateRange('2024-01-01', '2024-01-31');
        $commits = $branch->getCommitsInPeriod($range);

        $this->assertCount(2, $commits);
    }

    public function test_get_commits_in_period_returns_empty_collection_for_no_commits(): void
    {
        $branch = Branch::factory()->create();
        $range = new DateRange('2024-01-01', '2024-01-31');

        $commits = $branch->getCommitsInPeriod($range);

        $this->assertCount(0, $commits);
    }

    public function test_is_matched_returns_false_when_match_has_no_task(): void
    {
        $branch = Branch::factory()->create();
        MatchResult::factory()->create([
            'branch_id'        => $branch->id,
            'task_id'          => null,
            'confidence_level' => ConfidenceLevel::None,
            'resolved_by'      => ResolvedBy::User,
        ]);

        $this->assertFalse($branch->isMatched());
    }
}

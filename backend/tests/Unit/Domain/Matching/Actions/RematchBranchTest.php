<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Matching\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Actions\MatchBranch;
use App\Domain\Matching\Actions\RematchBranch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RematchBranchTest extends TestCase
{
    use RefreshDatabase;

    private RematchBranch $action;

    public function test_deletes_system_matches_and_rematches(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => null]);
        $oldMatch = MatchResult::factory()->create([
            'branch_id'        => $branch->id,
            'confidence_level' => ConfidenceLevel::None,
            'resolved_by'      => ResolvedBy::System,
        ]);

        $newResult = ($this->action)($branch);

        $this->assertDatabaseMissing('match_results', ['id' => $oldMatch->id]);
        $this->assertSame(ConfidenceLevel::None, $newResult->confidence_level);
        $this->assertSame(ResolvedBy::System, $newResult->resolved_by);
    }

    public function test_keeps_user_matches(): void
    {
        $task = Task::factory()->create(['bitrix24_task_id' => 500]);
        $branch = Branch::factory()->create(['parsed_task_number' => null]);

        $userMatch = MatchResult::factory()->create([
            'branch_id'        => $branch->id,
            'task_id'          => $task->id,
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::User,
        ]);

        ($this->action)($branch);

        $this->assertDatabaseHas('match_results', ['id' => $userMatch->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RematchBranch(new MatchBranch());
    }
}

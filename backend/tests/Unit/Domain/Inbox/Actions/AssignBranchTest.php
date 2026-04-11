<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inbox\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Inbox\Actions\AssignBranch;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AssignBranchTest extends TestCase
{
    use RefreshDatabase;

    private AssignBranch $action;

    public function test_assigns_branch_to_task(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        ($this->action)($branch->id, $task->id);

        $this->assertDatabaseHas('match_results', [
            'branch_id'   => $branch->id,
            'task_id'     => $task->id,
            'resolved_by' => ResolvedBy::User->value,
        ]);
    }

    public function test_deletes_system_matches_before_assign(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $systemMatch = MatchResult::factory()->create([
            'branch_id'   => $branch->id,
            'resolved_by' => ResolvedBy::System,
        ]);

        ($this->action)($branch->id, $task->id);

        $this->assertDatabaseMissing('match_results', ['id' => $systemMatch->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AssignBranch();
    }
}

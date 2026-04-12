<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Queries\GetTaskIdsFromCommits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetTaskIdsFromCommitsTest extends TestCase
{
    use RefreshDatabase;

    private GetTaskIdsFromCommits $query;

    public function test_returns_task_ids_linked_via_match_results(): void
    {
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        $commits = Commit::factory()->count(2)->create(['branch_id' => $branch->id]);

        $result = ($this->query)($commits);

        $this->assertSame([$task->id], $result);
    }

    public function test_returns_empty_array_when_no_branch_ids(): void
    {
        $commits = collect();

        $result = ($this->query)($commits);

        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_when_no_match_results(): void
    {
        $branch = Branch::factory()->create();
        $commits = Commit::factory()->count(2)->create(['branch_id' => $branch->id]);

        $result = ($this->query)($commits);

        $this->assertSame([], $result);
    }

    public function test_returns_distinct_task_ids(): void
    {
        $task = Task::factory()->create();
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch1->id, 'task_id' => $task->id]);
        MatchResult::factory()->create(['branch_id' => $branch2->id, 'task_id' => $task->id]);

        $commits = collect([
            Commit::factory()->create(['branch_id' => $branch1->id]),
            Commit::factory()->create(['branch_id' => $branch2->id]),
        ]);

        $result = ($this->query)($commits);

        $this->assertCount(1, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetTaskIdsFromCommits();
    }
}

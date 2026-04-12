<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Matching\Events;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Actions\MatchBranch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Events\BranchMatched;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class BranchMatchedTest extends TestCase
{
    use RefreshDatabase;

    private MatchBranch $action;

    public function test_match_branch_dispatches_event(): void
    {
        // GIVEN — a branch that can be matched automatically
        Event::fake([BranchMatched::class]);
        $branch = Branch::factory()->create(['parsed_task_number' => '42']);

        // WHEN — the MatchBranch action is invoked
        ($this->action)($branch);

        // THEN — a BranchMatched event is dispatched
        Event::assertDispatched(BranchMatched::class);
    }

    public function test_event_contains_match_result_and_branch(): void
    {
        // GIVEN — a branch with a matched task
        Event::fake([BranchMatched::class]);
        $task = Task::factory()->create(['bitrix24_task_id' => 77]);
        $branch = Branch::factory()->create(['parsed_task_number' => '77']);

        // WHEN — the MatchBranch action is invoked
        ($this->action)($branch);

        // THEN — the dispatched event carries the correct MatchResult and Branch
        Event::assertDispatched(BranchMatched::class, function (BranchMatched $event) use ($branch, $task): bool {
            return $event->branch->id === $branch->id
                && $event->matchResult->confidence_level === ConfidenceLevel::Auto
                && $event->matchResult->task_id === $task->id;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MatchBranch();
    }
}

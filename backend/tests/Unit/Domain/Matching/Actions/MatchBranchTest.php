<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Matching\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Actions\MatchBranch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MatchBranchTest extends TestCase
{
    use RefreshDatabase;

    private MatchBranch $action;

    public function test_auto_match_by_task_number(): void
    {
        $task = Task::factory()->create(['bitrix24_task_id' => 123]);
        $branch = Branch::factory()->create(['parsed_task_number' => '123']);

        $result = ($this->action)($branch);

        $this->assertSame(ConfidenceLevel::Auto, $result->confidence_level);
        $this->assertSame($task->id, $result->task_id);
        $this->assertSame($branch->id, $result->branch_id);
        $this->assertSame(ResolvedBy::System, $result->resolved_by);
    }

    public function test_probable_when_task_number_not_found(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => '999']);

        $result = ($this->action)($branch);

        $this->assertSame(ConfidenceLevel::Probable, $result->confidence_level);
        $this->assertNull($result->task_id);
    }

    public function test_none_when_no_task_number(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => null]);

        $result = ($this->action)($branch);

        $this->assertSame(ConfidenceLevel::None, $result->confidence_level);
        $this->assertNull($result->task_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MatchBranch();
    }
}

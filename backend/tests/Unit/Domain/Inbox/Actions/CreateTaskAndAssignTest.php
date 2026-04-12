<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inbox\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Inbox\Actions\AssignBranch;
use App\Domain\Inbox\Actions\CreateTaskAndAssign;
use App\Domain\Matching\Enums\ResolvedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateTaskAndAssignTest extends TestCase
{
    use RefreshDatabase;

    private CreateTaskAndAssign $action;

    public function test_creates_internal_task_and_assigns(): void
    {
        $branch = Branch::factory()->create();

        ($this->action)($branch->id, 'Internal work');

        $this->assertDatabaseHas('tasks', [
            'title'        => 'Internal work',
            'project_name' => 'Internal',
        ]);

        $this->assertDatabaseHas('match_results', [
            'branch_id'   => $branch->id,
            'resolved_by' => ResolvedBy::User->value,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateTaskAndAssign(new AssignBranch());
    }
}

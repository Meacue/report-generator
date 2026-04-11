<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\MatchResult;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_unlinked_branches(): void
    {
        // Branch without match -> should appear in inbox
        $unlinkedBranch = Branch::factory()->create();

        // Branch with auto match resolved by system -> should NOT appear
        $linkedBranch = Branch::factory()->create();
        MatchResult::factory()->auto()->create([
            'branch_id' => $linkedBranch->id,
        ]);

        // Branch with auto match resolved by user -> should NOT appear
        $userLinkedBranch = Branch::factory()->create();
        MatchResult::factory()->create([
            'branch_id'        => $userLinkedBranch->id,
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::User,
            'resolved_at'      => now(),
        ]);

        $response = $this->getJson('/api/inbox');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $unlinkedBranch->id);
    }

    public function test_list_filter_by_probable(): void
    {
        // Branch with probable match
        $probableBranch = Branch::factory()->create();
        MatchResult::factory()->probable()->create([
            'branch_id' => $probableBranch->id,
        ]);

        // Branch without any match
        Branch::factory()->create();

        // Branch with none match
        $noneBranch = Branch::factory()->create();
        MatchResult::factory()->unmatched()->create([
            'branch_id' => $noneBranch->id,
        ]);

        $response = $this->getJson('/api/inbox?filter=probable');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $probableBranch->id);
    }

    public function test_assign_branch_to_task(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $response = $this->postJson('/api/inbox/assign', [
            'branch_id' => $branch->id,
            'task_id'   => $task->bitrix24_task_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Assigned');

        $this->assertDatabaseHas('match_results', [
            'branch_id'        => $branch->id,
            'task_id'          => $task->id,
            'confidence_level' => ConfidenceLevel::Auto->value,
            'resolved_by'      => ResolvedBy::User->value,
        ]);
    }

    public function test_bulk_assign(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        $task1 = Task::factory()->create();
        $task2 = Task::factory()->create();

        $response = $this->postJson('/api/inbox/bulk-assign', [
            'assignments' => [
                ['branch_id' => $branch1->id, 'task_id' => $task1->bitrix24_task_id],
                ['branch_id' => $branch2->id, 'task_id' => $task2->bitrix24_task_id],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Bulk assigned');

        $this->assertDatabaseHas('match_results', [
            'branch_id'   => $branch1->id,
            'task_id'     => $task1->id,
            'resolved_by' => ResolvedBy::User->value,
        ]);
        $this->assertDatabaseHas('match_results', [
            'branch_id'   => $branch2->id,
            'task_id'     => $task2->id,
            'resolved_by' => ResolvedBy::User->value,
        ]);
    }

    public function test_ignore_branch(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/inbox/ignore', [
            'branch_id' => $branch->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Ignored');

        $this->assertDatabaseHas('match_results', [
            'branch_id'        => $branch->id,
            'task_id'          => null,
            'confidence_level' => ConfidenceLevel::None->value,
            'resolved_by'      => ResolvedBy::User->value,
        ]);
    }

    public function test_create_task_and_assign(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/inbox/create-task', [
            'branch_id' => $branch->id,
            'title'     => 'Internal refactoring work',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Task created and assigned');

        $this->assertDatabaseHas('tasks', [
            'title'            => 'Internal refactoring work',
            'bitrix24_task_id' => 0,
            'project_name'     => 'Internal',
        ]);

        $task = Task::where('title', 'Internal refactoring work')->first();
        $this->assertNotNull($task);

        $this->assertDatabaseHas('match_results', [
            'branch_id'        => $branch->id,
            'task_id'          => $task->id,
            'confidence_level' => ConfidenceLevel::Auto->value,
            'resolved_by'      => ResolvedBy::User->value,
        ]);
    }

    public function test_assigned_branch_disappears_from_inbox(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        // Branch should appear in inbox initially
        $response = $this->getJson('/api/inbox');
        $response->assertJsonCount(1, 'data');

        // Assign the branch
        $this->postJson('/api/inbox/assign', [
            'branch_id' => $branch->id,
            'task_id'   => $task->bitrix24_task_id,
        ]);

        // Branch should no longer appear in inbox
        $response = $this->getJson('/api/inbox');
        $response->assertJsonCount(0, 'data');
    }

    public function test_pagination(): void
    {
        Branch::factory()->count(5)->create();

        $response = $this->getJson('/api/inbox?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_assign_validation_errors(): void
    {
        $response = $this->postJson('/api/inbox/assign', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['branch_id', 'task_id']);
    }

    public function test_ignore_removes_existing_matches(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        // Create an existing probable match
        MatchResult::factory()->probable()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);

        $this->postJson('/api/inbox/ignore', [
            'branch_id' => $branch->id,
        ]);

        // Only the ignored match should remain
        $this->assertDatabaseCount('match_results', 1);
        $this->assertDatabaseHas('match_results', [
            'branch_id'   => $branch->id,
            'task_id'     => null,
            'resolved_by' => ResolvedBy::User->value,
        ]);
    }

    public function test_index_includes_commits_data(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id' => $branch->id,
            'message'   => 'feat: add new feature',
        ]);

        $response = $this->getJson('/api/inbox');

        $response->assertOk();
        $response->assertJsonPath('data.0.commits_count', 1);
        $response->assertJsonPath('data.0.last_commit', 'feat: add new feature');
    }
}

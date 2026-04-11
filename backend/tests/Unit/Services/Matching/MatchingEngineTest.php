<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Matching;

use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Models\Branch;
use App\Models\MatchResult;
use App\Models\Task;
use App\Services\Matching\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    private MatchingEngine $engine;

    public function test_auto_match_by_task_number(): void
    {
        $task = Task::factory()->create(['bitrix24_task_id' => 123]);
        $branch = Branch::factory()->create(['parsed_task_number' => '123']);

        $result = $this->engine->matchBranch($branch);

        $this->assertSame(ConfidenceLevel::Auto, $result->confidence_level);
        $this->assertSame($task->id, $result->task_id);
        $this->assertSame($branch->id, $result->branch_id);
        $this->assertSame(ResolvedBy::System, $result->resolved_by);
    }

    public function test_probable_when_task_number_not_found(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => '999']);

        $result = $this->engine->matchBranch($branch);

        $this->assertSame(ConfidenceLevel::Probable, $result->confidence_level);
        $this->assertNull($result->task_id);
        $this->assertSame($branch->id, $result->branch_id);
    }

    public function test_none_when_no_task_number(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => null]);

        $result = $this->engine->matchBranch($branch);

        $this->assertSame(ConfidenceLevel::None, $result->confidence_level);
        $this->assertNull($result->task_id);
        $this->assertSame($branch->id, $result->branch_id);
    }

    public function test_match_all_unmatched(): void
    {
        // auto: branch with task number matching a task
        $task = Task::factory()->create(['bitrix24_task_id' => 100]);
        Branch::factory()->create(['parsed_task_number' => '100']);

        // probable: branch with task number but no matching task
        Branch::factory()->create(['parsed_task_number' => '200']);

        // none: branch without task number
        Branch::factory()->create(['parsed_task_number' => null]);

        $results = $this->engine->matchAllUnmatched();

        $this->assertCount(3, $results);
        $this->assertSame(1, $results->where('confidence_level', ConfidenceLevel::Auto)->count());
        $this->assertSame(1, $results->where('confidence_level', ConfidenceLevel::Probable)->count());
        $this->assertSame(1, $results->where('confidence_level', ConfidenceLevel::None)->count());
    }

    public function test_rematch_deletes_system_matches(): void
    {
        $branch = Branch::factory()->create(['parsed_task_number' => null]);
        $oldMatch = MatchResult::factory()->create([
            'branch_id'        => $branch->id,
            'confidence_level' => ConfidenceLevel::None,
            'resolved_by'      => ResolvedBy::System,
        ]);

        $newResult = $this->engine->rematch($branch);

        $this->assertDatabaseMissing('match_results', ['id' => $oldMatch->id]);
        $this->assertSame(ConfidenceLevel::None, $newResult->confidence_level);
        $this->assertSame(ResolvedBy::System, $newResult->resolved_by);
    }

    public function test_rematch_keeps_user_matches(): void
    {
        $task = Task::factory()->create(['bitrix24_task_id' => 500]);
        $branch = Branch::factory()->create(['parsed_task_number' => null]);

        $userMatch = MatchResult::factory()->create([
            'branch_id'        => $branch->id,
            'task_id'          => $task->id,
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::User,
        ]);

        $this->engine->rematch($branch);

        $this->assertDatabaseHas('match_results', ['id' => $userMatch->id]);
    }

    public function test_auto_match_counts_in_log(): void
    {
        Log::spy();

        $task = Task::factory()->create(['bitrix24_task_id' => 300]);
        Branch::factory()->create(['parsed_task_number' => '300']);
        Branch::factory()->create(['parsed_task_number' => '999']);
        Branch::factory()->create(['parsed_task_number' => null]);

        $this->engine->matchAllUnmatched();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Matching completed'
                    && $context['total'] === 3
                    && $context['auto'] === 1
                    && $context['probable'] === 1
                    && $context['none'] === 1;
            })
            ->once();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MatchingEngine();
    }
}

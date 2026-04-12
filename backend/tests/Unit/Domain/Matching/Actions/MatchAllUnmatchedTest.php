<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Matching\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Actions\MatchAllUnmatched;
use App\Domain\Matching\Actions\MatchBranch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Events\BranchMatched;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class MatchAllUnmatchedTest extends TestCase
{
    use RefreshDatabase;

    private MatchAllUnmatched $action;

    public function test_matches_all_unmatched_branches(): void
    {
        Task::factory()->create(['bitrix24_task_id' => 100]);
        Branch::factory()->create(['parsed_task_number' => '100']);
        Branch::factory()->create(['parsed_task_number' => '200']);
        Branch::factory()->create(['parsed_task_number' => null]);

        $results = ($this->action)();

        $this->assertCount(3, $results);
        $this->assertSame(1, $results->where('confidence_level', ConfidenceLevel::Auto)->count());
        $this->assertSame(1, $results->where('confidence_level', ConfidenceLevel::Probable)->count());
        $this->assertSame(1, $results->where('confidence_level', ConfidenceLevel::None)->count());
    }

    public function test_logs_matching_results(): void
    {
        Log::spy();

        Task::factory()->create(['bitrix24_task_id' => 300]);
        Branch::factory()->create(['parsed_task_number' => '300']);
        Branch::factory()->create(['parsed_task_number' => '999']);
        Branch::factory()->create(['parsed_task_number' => null]);

        ($this->action)();

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

        Event::fake([BranchMatched::class]);

        $this->action = new MatchAllUnmatched(new MatchBranch());
    }
}

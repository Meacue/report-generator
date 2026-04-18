<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Domain\Bitrix24\DTOs\TimeEntryData;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Narrative\Services\LlmProviderInterface;
use App\Domain\Report\Models\Report;
use App\Domain\Settings\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

/**
 * e2e smoke-test for the Flow 2 integration:
 *   Mock Bitrix24 → run GenerateReport via HTTP → verify DB state → report generated.
 */
class ReportSyncFlowTest extends TestCase
{
    use RefreshDatabase;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    public function test_report_endpoint_returns_422_on_period_over_30_days(): void
    {
        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'weekly',
            'date_from' => '2026-01-01',
            'date_to'   => '2026-02-15', // 46 days — well over 30
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    public function test_report_endpoint_accepts_exactly_30_days(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => 42]);

        // Set up Bitrix24 client mock — called during sync
        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->andReturn([]);

        // Need a commit for HasDataForDateRange to pass
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-01-15 12:00:00',
        ]);

        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'weekly',
            'date_from' => '2026-01-01',
            'date_to'   => '2026-01-30', // exactly 30 days — allowed
        ]);

        $response->assertStatus(201);
    }

    public function test_full_sync_flow_external_task_and_time_entries_appear_in_db(): void
    {
        // GIVEN: settings with bitrix24_user_id configured
        Setting::factory()->create(['bitrix24_user_id' => 99]);

        // Use a 3-day range so tracked_at at noon is safely inside the period.
        // DateRange normalises string dates to midnight UTC, so the window is
        // [2026-03-09 00:00:00 UTC, 2026-03-11 00:00:00 UTC].
        $trackedAt = CarbonImmutable::parse('2026-03-10 12:00:00', 'UTC');

        // Bitrix24 returns one time entry for task 5001
        $timeEntryData = new TimeEntryData(
            bitrix24EntryId: 9001,
            bitrix24TaskId: 5001,
            bitrix24UserId: '99',
            seconds: 3600,
            comment: 'Smoke test',
            trackedAt: $trackedAt,
            sourceCreatedAt: $trackedAt,
        );

        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn([$timeEntryData]);

        // Task 5001 is not in the DB yet → tryGetTask is called
        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(5001)
            ->andReturn([
                'id'             => '5001',
                'title'          => 'External smoke task',
                'status'         => '3',
                'statusComplete' => '0',
                'groupId'        => '7',
                'group'          => ['id' => '7', 'name' => 'SmokeProject'],
                'closedDate'     => null,
                'url'            => '/tasks/5001/',
                'createdBy'      => '1',
                'responsibleId'  => '1',
                'accomplices'    => [],
                'auditors'       => [],
            ]);

        // Need at least one commit so HasDataForDateRange passes
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 14:00:00',
            'message'      => 'feat: smoke test commit',
        ]);

        // WHEN: generate report via API (3-day range: 2026-03-09 to 2026-03-11)
        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'weekly',
            'date_from' => '2026-03-09',
            'date_to'   => '2026-03-11',
        ]);

        // THEN: report is created
        $response->assertStatus(201);

        /** @var int $reportId */
        $reportId = $response->json('data.id');

        $report = Report::findOrFail($reportId);
        $this->assertSame('generated', $report->status->value);

        // AND: the external task was created in the tasks table
        $this->assertDatabaseHas('tasks', [
            'bitrix24_task_id' => 5001,
            'title'            => 'External smoke task',
            'is_external'      => true,
        ]);

        // AND: the time entry is in the DB
        $this->assertDatabaseHas('task_time_entries', [
            'bitrix24_entry_id' => 9001,
            'bitrix24_task_id'  => 5001,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(LlmProviderInterface::class, new MockLlmProvider());

        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->app->instance(Bitrix24ClientInterface::class, $this->bitrix24Client);
    }
}

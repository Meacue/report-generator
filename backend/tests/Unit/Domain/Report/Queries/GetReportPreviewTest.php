<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Actions\GenerateReport;
use App\Domain\Report\DTOs\ReportPreview;
use App\Domain\Report\Queries\GetCommitsForDate;
use App\Domain\Report\Queries\GetReportPreview;
use App\Domain\Report\Queries\GetTaskIdsFromCommits;
use App\Domain\Report\Queries\GetTaskTimeBreakdown;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Actions\SyncBitrix24ForReport;
use App\Domain\Sync\DTOs\SyncBitrix24ForReportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class GetReportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private GetReportPreview $query;

    public function test_returns_structured_data(): void
    {
        $task = Task::factory()->create(['project_name' => 'TestProject']);
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 10:00:00',
            'message'      => 'feat: add user auth',
        ]);

        $syncMock = Mockery::mock(SyncBitrix24ForReport::class);
        $syncMock->shouldReceive('__invoke')->andReturn(new SyncBitrix24ForReportResult(0, 0));
        $generateReport = new GenerateReport(new GetCommitsForDate(), new GetTaskIdsFromCommits(), $syncMock);
        $report = $generateReport('daily', new DateRange('2026-03-10', '2026-03-10'));
        $preview = ($this->query)($report);

        $this->assertInstanceOf(ReportPreview::class, $preview);
        $this->assertSame('daily', $preview->type);
        $this->assertSame('2026-03-10', $preview->dateFrom);
        $this->assertCount(1, $preview->days);
    }

    public function test_populates_seconds_tracked_from_breakdown_query(): void
    {
        // GIVEN: a task with time entries inside the report period.
        $task = Task::factory()->create([
            'bitrix24_task_id' => 57706,
            'project_name'     => 'Proj',
        ]);
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-04-10 10:00:00',
        ]);

        // Time entry for the task (user id comes from GetTaskTimeBreakdown → Setting::first()).
        // Use an explicit userId so we can create an entry without a Setting row.
        // (GetTaskTimeBreakdown falls back to Setting only when no userId is passed, so
        // we exercise that path in GetTaskTimeBreakdownTest. Here we just verify
        // that secondsTracked is wired through the preview.)
        // We call the query directly with userId=null; so create a Setting first.
        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => '1',
            'seconds'          => 3600,
            'tracked_at'       => '2026-04-10 09:00:00',
        ]);

        $syncMock = Mockery::mock(SyncBitrix24ForReport::class);
        $syncMock->shouldReceive('__invoke')->andReturn(new SyncBitrix24ForReportResult(0, 0));
        $generateReport = new GenerateReport(new GetCommitsForDate(), new GetTaskIdsFromCommits(), $syncMock);
        $report = $generateReport('daily', new DateRange('2026-04-10', '2026-04-10'));

        // WHEN:
        $preview = ($this->query)($report);

        // THEN: the task DTO carries the tracked seconds.
        $this->assertNotEmpty($preview->tasks);
        $topLevelTask = $preview->tasks[0];
        $this->assertNotNull($topLevelTask->task);
        $this->assertSame(3600, $topLevelTask->task->secondsTracked);
    }

    public function test_seconds_tracked_is_null_when_no_entries(): void
    {
        // GIVEN: a task with NO time entries.
        $task = Task::factory()->create(['bitrix24_task_id' => 99999, 'project_name' => 'Proj']);
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-04-10 10:00:00',
        ]);

        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

        $syncMock = Mockery::mock(SyncBitrix24ForReport::class);
        $syncMock->shouldReceive('__invoke')->andReturn(new SyncBitrix24ForReportResult(0, 0));
        $generateReport = new GenerateReport(new GetCommitsForDate(), new GetTaskIdsFromCommits(), $syncMock);
        $report = $generateReport('daily', new DateRange('2026-04-10', '2026-04-10'));

        // WHEN:
        $preview = ($this->query)($report);

        // THEN: secondsTracked is null (no entries for this task).
        $this->assertNotEmpty($preview->tasks);
        $topLevelTask = $preview->tasks[0];
        $this->assertNotNull($topLevelTask->task);
        $this->assertNull($topLevelTask->task->secondsTracked);
    }

    public function test_stub_task_shows_fallback_title(): void
    {
        // GIVEN: a stub task (external, title=null) linked to a report day.
        $task = Task::factory()->create([
            'bitrix24_task_id' => 57706,
            'title'            => null,
            'is_external'      => true,
        ]);
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-04-10 10:00:00',
        ]);

        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

        $syncMock = Mockery::mock(SyncBitrix24ForReport::class);
        $syncMock->shouldReceive('__invoke')->andReturn(new SyncBitrix24ForReportResult(0, 0));
        $generateReport = new GenerateReport(new GetCommitsForDate(), new GetTaskIdsFromCommits(), $syncMock);
        $report = $generateReport('daily', new DateRange('2026-04-10', '2026-04-10'));

        // WHEN:
        $preview = ($this->query)($report);

        // THEN: the day task displays the fallback label with the bitrix24 id.
        $this->assertNotEmpty($preview->days);
        $dayTasks = $preview->days[0]->tasks;
        $this->assertNotEmpty($dayTasks);
        $this->assertSame('#57706 (без названия)', $dayTasks[0]->title);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetReportPreview(new GetTaskTimeBreakdown());
    }
}

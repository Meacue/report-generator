<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Report;

use App\Enums\ReportDaySource;
use App\Enums\ReportStatus;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\MatchResult;
use App\Models\Task;
use App\Services\Report\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ReportBuilder $builder;

    public function test_generate_creates_report_with_days(): void
    {
        $dateFrom = '2026-03-09';
        $dateTo = '2026-03-11';

        $report = $this->builder->generate('weekly', $dateFrom, $dateTo);

        $this->assertSame(ReportStatus::Generated, $report->status);
        $this->assertSame($dateFrom, $report->date_from->format('Y-m-d'));
        $this->assertSame($dateTo, $report->date_to->format('Y-m-d'));

        $report->load('reportDays');
        $this->assertCount(3, $report->reportDays);

        $dates = $report->reportDays->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        $this->assertContains('2026-03-09', $dates);
        $this->assertContains('2026-03-10', $dates);
        $this->assertContains('2026-03-11', $dates);
    }

    public function test_generate_links_tasks_to_report(): void
    {
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 14:00:00',
        ]);

        $report = $this->builder->generate('daily', '2026-03-10', '2026-03-10');

        $report->load('reportTasks');
        $this->assertCount(1, $report->reportTasks);
        $this->assertSame($task->id, $report->reportTasks->first()?->task_id);
    }

    public function test_preview_returns_structured_data(): void
    {
        $task = Task::factory()->create(['project_name' => 'TestProject']);
        $branch = Branch::factory()->create();
        MatchResult::factory()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 10:00:00',
            'message'      => 'feat: add user auth',
        ]);

        $report = $this->builder->generate('daily', '2026-03-10', '2026-03-10');
        $preview = $this->builder->getPreview($report);

        $this->assertArrayHasKey('id', $preview);
        $this->assertArrayHasKey('type', $preview);
        $this->assertArrayHasKey('date_from', $preview);
        $this->assertArrayHasKey('date_to', $preview);
        $this->assertArrayHasKey('status', $preview);
        $this->assertArrayHasKey('days', $preview);

        $this->assertSame('daily', $preview['type']);
        $this->assertSame('2026-03-10', $preview['date_from']);
        $this->assertSame('2026-03-10', $preview['date_to']);
        $this->assertSame('generated', $preview['status']);

        $this->assertCount(1, $preview['days']);
        $day = $preview['days'][0];
        $this->assertSame('2026-03-10', $day['date']);
        $this->assertSame('commits', $day['source']);
        $this->assertFalse($day['is_edited']);
        $this->assertIsArray($day['tasks']);
    }

    public function test_day_without_commits_gets_fallback_source(): void
    {
        $report = $this->builder->generate('daily', '2026-03-10', '2026-03-10');

        $report->load('reportDays');
        $reportDay = $report->reportDays->first();

        $this->assertNotNull($reportDay);
        $this->assertSame(ReportDaySource::Bitrix24Fallback, $reportDay->source);
        $this->assertNull($reportDay->narrative);
    }

    public function test_placeholder_narrative_from_commits(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 10:00:00',
            'message'      => 'feat: add login endpoint',
        ]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 12:00:00',
            'message'      => 'fix: password validation regex',
        ]);

        $report = $this->builder->generate('daily', '2026-03-10', '2026-03-10');

        $report->load('reportDays');
        $reportDay = $report->reportDays->first();

        $this->assertNotNull($reportDay);
        $this->assertNotNull($reportDay->narrative);
        $this->assertStringContainsString('Выполнены коммиты:', $reportDay->narrative);
        $this->assertStringContainsString('feat: add login endpoint', $reportDay->narrative);
        $this->assertStringContainsString('fix: password validation regex', $reportDay->narrative);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ReportBuilder();
    }
}

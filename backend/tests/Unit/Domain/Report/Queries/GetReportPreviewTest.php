<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Actions\GenerateReport;
use App\Domain\Report\Queries\GetCommitsForDate;
use App\Domain\Report\DTOs\ReportPreview;
use App\Domain\Report\Queries\GetReportPreview;
use App\Domain\Report\Queries\GetTaskIdsFromCommits;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $generateReport = new GenerateReport(new GetCommitsForDate(), new GetTaskIdsFromCommits());
        $report = $generateReport('daily', new DateRange('2026-03-10', '2026-03-10'));
        $preview = ($this->query)($report);

        $this->assertInstanceOf(ReportPreview::class, $preview);
        $this->assertSame('daily', $preview->type);
        $this->assertSame('2026-03-10', $preview->dateFrom);
        $this->assertCount(1, $preview->days);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetReportPreview();
    }
}

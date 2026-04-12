<?php

declare(strict_types=1);

namespace App\Domain\Report\Actions;

use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Events\ReportGenerated;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Enums\ReportType;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\Queries\GetCommitsForDate;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Bitrix24\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class GenerateReport
{
    public function __construct(
        private GetCommitsForDate $getCommitsForDate,
    ) {
    }

    public function __invoke(string $type, DateRange $dateRange): Report
    {
        $report = Report::create([
            'type'      => ReportType::from($type),
            'date_from' => $dateRange->from->toDateString(),
            'date_to'   => $dateRange->to->toDateString(),
            'status'    => ReportStatus::Draft,
        ]);

        $period = $dateRange->toPeriod();
        /** @var array<int, ReportTask> $reportTaskMap */
        $reportTaskMap = [];

        /** @var Carbon $date */
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');

            $commits = ($this->getCommitsForDate)($dateString);

            if ($commits->isEmpty()) {
                $report->addDay($dateString, ReportDaySource::Bitrix24Fallback);

                continue;
            }

            $narrative = $this->buildPlaceholderNarrative($commits);

            $reportDay = $report->addDay($dateString, ReportDaySource::Commits, $narrative);

            $taskIds = $this->findTaskIdsFromCommits($commits);

            foreach ($taskIds as $taskId) {
                if (! isset($reportTaskMap[$taskId])) {
                    $task = Task::find($taskId);
                    $reportTaskMap[$taskId] = $report->addTask($taskId, $task?->project_name);
                }

                $reportDay->linkTask($reportTaskMap[$taskId]);
            }
        }

        $report->markAsGenerated();

        ReportGenerated::dispatch($report);

        return $report->refresh();
    }

    /**
     * @param  Collection<int, Commit>  $commits
     */
    private function buildPlaceholderNarrative(Collection $commits): string
    {
        $messages = $commits->pluck('message')->implode(', ');

        return 'Выполнены коммиты: ' . $messages;
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @return list<int>
     */
    private function findTaskIdsFromCommits(Collection $commits): array
    {
        /** @var list<int> $branchIds */
        $branchIds = $commits->pluck('branch_id')->unique()->filter()->values()->all();

        if ($branchIds === []) {
            return [];
        }

        /** @var list<int> */
        return MatchResult::whereIn('branch_id', $branchIds)
            ->whereNotNull('task_id')
            ->distinct()
            ->pluck('task_id')
            ->all();
    }
}

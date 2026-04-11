<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Enums\ReportType;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Bitrix24\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportBuilder implements ReportBuilderInterface
{
    public function generate(string $type, DateRange $dateRange): Report
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

            $commits = $this->findCommitsForDate($dateString);

            if ($commits->isEmpty()) {
                $this->createReportDay(
                    $report,
                    $dateString,
                    ReportDaySource::Bitrix24Fallback,
                    null,
                );

                continue;
            }

            $narrative = $this->buildPlaceholderNarrative($commits);

            $reportDay = $this->createReportDay(
                $report,
                $dateString,
                ReportDaySource::Commits,
                $narrative,
            );

            $taskIds = $this->findTaskIdsFromCommits($commits);

            foreach ($taskIds as $taskId) {
                if (! isset($reportTaskMap[$taskId])) {
                    $reportTaskMap[$taskId] = $this->createReportTask($report, $taskId);
                }

                ReportDayTask::create([
                    'report_day_id'  => $reportDay->id,
                    'report_task_id' => $reportTaskMap[$taskId]->id,
                ]);
            }
        }

        $report->markAsGenerated();

        return $report->refresh();
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     date_from: string,
     *     date_to: string,
     *     status: string,
     *     days: array<int, array{
     *         date: string,
     *         narrative: string|null,
     *         source: string,
     *         is_edited: bool,
     *         tasks: array<int, array{
     *             id: int|null,
     *             title: string,
     *             project_name: string|null,
     *             narrative: string|null,
     *             is_edited: bool
     *         }>
     *     }>,
     *     tasks: list<array{id: int, task_id: int|null, narrative: string|null, project_name: string, is_edited: bool, task: array{id: int, bitrix24_task_id: int|null, title: string, status: string}|null}>
     * }
     */
    public function getPreview(Report $report): array
    {
        $report->load(['reportDays.reportDayTasks.reportTask.task', 'reportTasks.task']);

        /** @var list<array{date: string, narrative: string|null, source: string, is_edited: bool, tasks: array<int, array{id: int|null, title: string, project_name: string|null, narrative: string|null, is_edited: bool}>}> $days */
        $days = [];

        foreach ($report->reportDays as $reportDay) {
            $dateString = $reportDay->date->format('Y-m-d');

            $dayTasks = $this->findTasksForDay($reportDay);

            $days[] = [
                'date'      => $dateString,
                'narrative' => $reportDay->narrative,
                'source'    => $reportDay->source->value,
                'is_edited' => $reportDay->is_edited,
                'tasks'     => $dayTasks,
            ];
        }

        return [
            'id'        => $report->id,
            'type'      => $report->type->value,
            'date_from' => $report->date_from->format('Y-m-d'),
            'date_to'   => $report->date_to->format('Y-m-d'),
            'status'    => $report->status->value,
            'days'      => $days,
            'tasks'     => $this->buildTopLevelTasks($report),
        ];
    }

    /**
     * @return list<array{id: int, task_id: int|null, narrative: string|null, project_name: string, is_edited: bool, task: array{id: int, bitrix24_task_id: int|null, title: string, status: string}|null}>
     */
    private function buildTopLevelTasks(Report $report): array
    {
        $tasks = [];

        foreach ($report->reportTasks as $reportTask) {
            $tasks[] = [
                'id'           => $reportTask->id,
                'task_id'      => $reportTask->task_id,
                'narrative'    => $reportTask->narrative,
                'project_name' => $reportTask->project_name ?? '',
                'is_edited'    => $reportTask->is_edited,
                'task'         => $reportTask->task ? [
                    'id'               => $reportTask->task->id,
                    'bitrix24_task_id' => $reportTask->task->bitrix24_task_id,
                    'title'            => $reportTask->task->title,
                    'status'           => $reportTask->task->status->value,
                ] : null,
            ];
        }

        return $tasks;
    }

    /**
     * Find commits for a specific date.
     *
     * @return Collection<int, Commit>
     */
    private function findCommitsForDate(string $date): Collection
    {
        return Commit::whereDate('committed_at', $date)->get();
    }

    /**
     * Build a placeholder narrative from commit messages.
     *
     * @param  Collection<int, Commit>  $commits
     */
    private function buildPlaceholderNarrative(Collection $commits): string
    {
        $messages = $commits->pluck('message')->implode(', ');

        return 'Выполнены коммиты: ' . $messages;
    }

    /**
     * Find task IDs linked to commits via match_results (commit -> branch -> match_result -> task).
     *
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

    private function createReportDay(
        Report $report,
        string $date,
        ReportDaySource $source,
        ?string $narrative,
    ): ReportDay {
        /** @var ReportDay */
        return $report->reportDays()->create([
            'date'      => $date,
            'source'    => $source,
            'narrative' => $narrative,
            'is_edited' => false,
        ]);
    }

    private function createReportTask(Report $report, int $taskId): ReportTask
    {
        $task = Task::find($taskId);

        /** @var ReportTask */
        return $report->reportTasks()->create([
            'task_id'      => $taskId,
            'narrative'    => null,
            'project_name' => $task?->project_name,
            'is_edited'    => false,
        ]);
    }

    /**
     * Find tasks linked to a specific day through the report_day_tasks pivot.
     *
     * @return list<array{
     *     id: int|null,
     *     title: string,
     *     project_name: string|null,
     *     narrative: string|null,
     *     is_edited: bool
     * }>
     */
    private function findTasksForDay(ReportDay $reportDay): array
    {
        $tasks = [];

        foreach ($reportDay->reportDayTasks as $rdt) {
            /** @var ReportTask|null $reportTask */
            $reportTask = $rdt->reportTask;

            if ($reportTask === null) {
                continue;
            }

            $tasks[] = [
                'id'           => $reportTask->task_id,
                'title'        => $reportTask->task->title ?? '',
                'project_name' => $reportTask->project_name,
                'narrative'    => $rdt->narrative ?? $reportTask->narrative,
                'is_edited'    => $rdt->is_edited,
            ];
        }

        if ($tasks !== []) {
            return $tasks;
        }

        return $this->buildFallbackTaskFromNarrative($reportDay);
    }

    /**
     * Build a fallback task entry for days with narrative but no linked tasks.
     *
     * @return list<array{id: int|null, title: string, project_name: string|null, narrative: string|null, is_edited: bool}>
     */
    private function buildFallbackTaskFromNarrative(ReportDay $reportDay): array
    {
        if ($reportDay->narrative === null || $reportDay->narrative === '') {
            return [];
        }

        return [
            [
                'id'           => null,
                'title'        => 'Прочие работы',
                'project_name' => null,
                'narrative'    => $reportDay->narrative,
                'is_edited'    => $reportDay->is_edited,
            ],
        ];
    }
}

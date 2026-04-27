<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\DTOs\PromptExportData;
use App\Domain\Report\DTOs\PromptExportDay;
use App\Domain\Report\DTOs\PromptExportDayTask;
use App\Domain\Report\DTOs\PromptExportPeriodTask;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\Queries\GetTaskTimeTimeline;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Assembles the structured payload that backs the downloadable AI prompt file.
 *
 * Merges report tasks/days with the per-day time timeline so that orphan
 * Bitrix24 tasks (logged time but no commits) still appear with their titles
 * and that days without any activity are explicitly marked as empty.
 */
final readonly class PromptExportDataAssembler
{
    private const array DAY_OF_WEEK_LABELS = [
        'Monday'    => 'Понедельник',
        'Tuesday'   => 'Вторник',
        'Wednesday' => 'Среда',
        'Thursday'  => 'Четверг',
        'Friday'    => 'Пятница',
        'Saturday'  => 'Суббота',
        'Sunday'    => 'Воскресенье',
    ];

    public function __construct(
        private GetTaskTimeTimeline $getTaskTimeTimeline,
        private NarrativeSupport $narrativeSupport,
    ) {
    }

    public function assemble(Report $report): PromptExportData
    {
        $report->load([
            'reportTasks.task.matchResults',
            'reportDays.reportDayTasks.reportTask.task.matchResults',
        ]);

        $period = $report->getDateRange();
        $timeline = ($this->getTaskTimeTimeline)($period);

        // Map: bitrix24_task_id => first ReportTask owning that task.
        $reportTasksByBitrixId = $this->mapReportTasksByBitrixId($report);

        $orphanIds = $this->collectOrphanBitrixIds($timeline, $reportTasksByBitrixId);
        $orphanTasksByBitrixId = $this->loadOrphanTasks($orphanIds);

        $periodTasks = $this->buildPeriodTasks(
            $reportTasksByBitrixId,
            $orphanTasksByBitrixId,
            $timeline,
        );

        $days = $this->buildDays($report, $timeline, $reportTasksByBitrixId, $orphanTasksByBitrixId);

        return new PromptExportData($periodTasks, $days);
    }

    /**
     * @return array<int, ReportTask>
     */
    private function mapReportTasksByBitrixId(Report $report): array
    {
        $map = [];

        foreach ($report->reportTasks as $reportTask) {
            $task = $reportTask->task;
            if ($task === null || $task->bitrix24_task_id === null) {
                continue;
            }

            $bid = $task->bitrix24_task_id;
            if (! isset($map[$bid])) {
                $map[$bid] = $reportTask;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<int, int>>  $timeline
     * @param  array<int, ReportTask>  $reportTasksByBitrixId
     * @return list<int>
     */
    private function collectOrphanBitrixIds(array $timeline, array $reportTasksByBitrixId): array
    {
        $ids = [];

        foreach ($timeline as $dayTasks) {
            foreach ($dayTasks as $bid => $_seconds) {
                if (! isset($reportTasksByBitrixId[$bid]) && ! isset($ids[$bid])) {
                    $ids[$bid] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  list<int>  $orphanIds
     * @return array<int, Task>
     */
    private function loadOrphanTasks(array $orphanIds): array
    {
        if ($orphanIds === []) {
            return [];
        }

        /** @var Collection<int, Task> $tasks */
        $tasks = Task::whereIn('bitrix24_task_id', $orphanIds)
            ->withTrashed()
            ->get();

        $map = [];
        foreach ($tasks as $task) {
            if ($task->bitrix24_task_id === null) {
                continue;
            }
            $bid = $task->bitrix24_task_id;
            if (! isset($map[$bid])) {
                $map[$bid] = $task;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, ReportTask>  $reportTasksByBitrixId
     * @param  array<int, Task>  $orphanTasksByBitrixId
     * @param  array<string, array<int, int>>  $timeline
     * @return list<PromptExportPeriodTask>
     */
    private function buildPeriodTasks(
        array $reportTasksByBitrixId,
        array $orphanTasksByBitrixId,
        array $timeline,
    ): array {
        /** @var list<PromptExportPeriodTask> $periodTasks */
        $periodTasks = [];

        foreach ($reportTasksByBitrixId as $bid => $reportTask) {
            $task = $reportTask->task;
            if ($task === null) {
                continue;
            }

            $title = $task->title ?? "#{$bid} (без названия)";
            $projectName = $reportTask->project_name ?? $task->project_name;
            $status = $task->status->value;
            $totalSeconds = $this->sumTaskSecondsAcrossPeriod($timeline, $bid);

            $periodTasks[] = new PromptExportPeriodTask(
                bitrix24TaskId: $bid,
                title: $title,
                projectName: $projectName,
                status: $status,
                totalSeconds: $totalSeconds,
            );
        }

        foreach ($orphanTasksByBitrixId as $bid => $task) {
            $title = $task->title ?? "#{$bid} (нет в Bitrix24)";
            $totalSeconds = $this->sumTaskSecondsAcrossPeriod($timeline, $bid);

            $periodTasks[] = new PromptExportPeriodTask(
                bitrix24TaskId: $bid,
                title: $title,
                projectName: $task->project_name,
                status: null,
                totalSeconds: $totalSeconds,
            );
        }

        // Orphan ids that are not even present in the tasks table.
        $missingIds = $this->findMissingOrphanIds($timeline, $reportTasksByBitrixId, $orphanTasksByBitrixId);
        foreach ($missingIds as $bid) {
            $totalSeconds = $this->sumTaskSecondsAcrossPeriod($timeline, $bid);
            $periodTasks[] = new PromptExportPeriodTask(
                bitrix24TaskId: $bid,
                title: "#{$bid} (нет в Bitrix24)",
                projectName: null,
                status: null,
                totalSeconds: $totalSeconds,
            );
        }

        return $periodTasks;
    }

    /**
     * @param  array<string, array<int, int>>  $timeline
     */
    private function sumTaskSecondsAcrossPeriod(array $timeline, int $bitrix24TaskId): int
    {
        $total = 0;
        foreach ($timeline as $dayTasks) {
            if (isset($dayTasks[$bitrix24TaskId])) {
                $total += $dayTasks[$bitrix24TaskId];
            }
        }

        return $total;
    }

    /**
     * @param  array<string, array<int, int>>  $timeline
     * @param  array<int, ReportTask>  $reportTasksByBitrixId
     * @param  array<int, Task>  $orphanTasksByBitrixId
     * @return list<int>
     */
    private function findMissingOrphanIds(
        array $timeline,
        array $reportTasksByBitrixId,
        array $orphanTasksByBitrixId,
    ): array {
        $missing = [];
        foreach ($timeline as $dayTasks) {
            foreach ($dayTasks as $bid => $_seconds) {
                if (isset($reportTasksByBitrixId[$bid]) || isset($orphanTasksByBitrixId[$bid])) {
                    continue;
                }
                $missing[$bid] = true;
            }
        }

        return array_keys($missing);
    }

    /**
     * @param  array<string, array<int, int>>  $timeline
     * @param  array<int, ReportTask>  $reportTasksByBitrixId
     * @param  array<int, Task>  $orphanTasksByBitrixId
     * @return list<PromptExportDay>
     */
    private function buildDays(
        Report $report,
        array $timeline,
        array $reportTasksByBitrixId,
        array $orphanTasksByBitrixId,
    ): array {
        $reportDayByYmd = $this->mapReportDaysByYmd($report);

        /** @var list<PromptExportDay> $days */
        $days = [];

        foreach ($report->getDateRange()->toPeriod() as $date) {
            /** @var CarbonInterface $date */
            $ymd = $date->format('Y-m-d');
            $reportDay = $reportDayByYmd[$ymd] ?? null;

            $dayTimeline = $timeline[$ymd] ?? [];

            // Collect bitrix24_task_ids relevant to this day from both timeline and report-day tasks.
            $taskIds = [];
            foreach (array_keys($dayTimeline) as $bid) {
                $taskIds[$bid] = true;
            }
            if ($reportDay !== null) {
                foreach ($reportDay->reportDayTasks as $reportDayTask) {
                    $task = $reportDayTask->reportTask?->task;
                    if ($task !== null && $task->bitrix24_task_id !== null) {
                        $taskIds[$task->bitrix24_task_id] = true;
                    }
                }
            }

            $bitrixIds = array_keys($taskIds);
            $isEmpty = $bitrixIds === [];

            $dayOfWeek = self::DAY_OF_WEEK_LABELS[$date->format('l')] ?? $date->format('l');

            if ($isEmpty) {
                $days[] = new PromptExportDay(
                    date: $ymd,
                    dayOfWeek: $dayOfWeek,
                    source: $reportDay?->source,
                    isEmpty: true,
                    tasks: [],
                );

                continue;
            }

            // Build a map of report-day-tasks indexed by bitrix24_task_id for quick lookup.
            $reportDayTaskByBitrixId = $reportDay !== null
                ? $this->mapReportDayTasksByBitrixId($reportDay)
                : [];

            /** @var list<PromptExportDayTask> $tasks */
            $tasks = [];
            foreach ($bitrixIds as $bid) {
                $tasks[] = $this->buildDayTask(
                    $bid,
                    $dayTimeline[$bid] ?? 0,
                    $reportDay,
                    $reportDayTaskByBitrixId[$bid] ?? null,
                    $reportTasksByBitrixId[$bid] ?? null,
                    $orphanTasksByBitrixId[$bid] ?? null,
                );
            }

            $days[] = new PromptExportDay(
                date: $ymd,
                dayOfWeek: $dayOfWeek,
                source: $reportDay?->source,
                isEmpty: false,
                tasks: $tasks,
            );
        }

        return $days;
    }

    /**
     * @return array<string, ReportDay>
     */
    private function mapReportDaysByYmd(Report $report): array
    {
        $map = [];
        foreach ($report->reportDays as $reportDay) {
            $ymd = $reportDay->date->format('Y-m-d');
            $map[$ymd] = $reportDay;
        }

        return $map;
    }

    /**
     * @return array<int, ReportDayTask>
     */
    private function mapReportDayTasksByBitrixId(ReportDay $reportDay): array
    {
        $map = [];
        foreach ($reportDay->reportDayTasks as $reportDayTask) {
            $task = $reportDayTask->reportTask?->task;
            if ($task === null || $task->bitrix24_task_id === null) {
                continue;
            }
            $bid = $task->bitrix24_task_id;
            if (! isset($map[$bid])) {
                $map[$bid] = $reportDayTask;
            }
        }

        return $map;
    }

    private function buildDayTask(
        int $bitrix24TaskId,
        int $seconds,
        ?ReportDay $reportDay,
        ?ReportDayTask $reportDayTask,
        ?ReportTask $reportTask,
        ?Task $orphanTask,
    ): PromptExportDayTask {
        $linkedTask = $reportTask?->task;

        if ($linkedTask !== null) {
            $title = $linkedTask->title ?? "#{$bitrix24TaskId} (без названия)";
            $projectName = $reportTask->project_name ?? $linkedTask->project_name;
        } elseif ($orphanTask !== null) {
            $title = $orphanTask->title ?? "#{$bitrix24TaskId} (нет в Bitrix24)";
            $projectName = $orphanTask->project_name;
        } else {
            $title = "#{$bitrix24TaskId} (нет в Bitrix24)";
            $projectName = null;
        }

        $commits = ($reportDay !== null && $reportTask !== null)
            ? $this->loadCommitsForDayTask($reportDay, $reportTask)
            : [];

        $narrative = $reportDayTask?->narrative;

        return new PromptExportDayTask(
            bitrix24TaskId: $bitrix24TaskId,
            title: $title,
            projectName: $projectName,
            seconds: $seconds,
            commits: $commits,
            narrative: $narrative,
        );
    }

    /**
     * @return list<string>
     */
    private function loadCommitsForDayTask(ReportDay $reportDay, ReportTask $reportTask): array
    {
        return array_values($this->narrativeSupport->getCommitMessagesForDayTask($reportDay, $reportTask));
    }
}

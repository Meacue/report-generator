<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Report\DTOs\ReportPreview;
use App\Domain\Report\DTOs\ReportPreviewBitrix24Task;
use App\Domain\Report\DTOs\ReportPreviewDay;
use App\Domain\Report\DTOs\ReportPreviewDayTask;
use App\Domain\Report\DTOs\ReportPreviewTask;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;

final readonly class GetReportPreview
{
    public function __construct(
        private GetTaskTimeBreakdown $getTaskTimeBreakdown,
    ) {
    }

    public function __invoke(Report $report): ReportPreview
    {
        $report->load(['reportDays.reportDayTasks.reportTask.task', 'reportTasks.task']);

        $breakdown = $this->getTaskTimeBreakdown->__invoke($report->getDateRange());

        /** @var list<ReportPreviewDay> $days */
        $days = [];

        foreach ($report->reportDays as $reportDay) {
            $days[] = new ReportPreviewDay(
                date: $reportDay->date->format('Y-m-d'),
                narrative: $reportDay->narrative,
                source: $reportDay->source->value,
                isEdited: $reportDay->is_edited,
                tasks: $this->findTasksForDay($reportDay),
            );
        }

        return new ReportPreview(
            id: $report->id,
            type: $report->type->value,
            dateFrom: $report->date_from->format('Y-m-d'),
            dateTo: $report->date_to->format('Y-m-d'),
            status: $report->status->value,
            days: $days,
            tasks: $this->buildTopLevelTasks($report, $breakdown),
        );
    }

    /**
     * @param  array<int, int>  $breakdown
     * @return list<ReportPreviewTask>
     */
    private function buildTopLevelTasks(Report $report, array $breakdown): array
    {
        $tasks = [];

        foreach ($report->reportTasks as $reportTask) {
            $task = $reportTask->task;
            $bitrix24TaskId = $task !== null ? $task->bitrix24_task_id : null;
            $secondsTracked = ($bitrix24TaskId !== null && isset($breakdown[$bitrix24TaskId]))
                ? $breakdown[$bitrix24TaskId]
                : null;

            $tasks[] = new ReportPreviewTask(
                id: $reportTask->id,
                taskId: $reportTask->task_id,
                narrative: $reportTask->narrative,
                projectName: $reportTask->project_name ?? '',
                isEdited: $reportTask->is_edited,
                task: $task !== null
                    ? new ReportPreviewBitrix24Task(
                        id: $task->id,
                        bitrix24TaskId: $task->bitrix24_task_id,
                        title: $task->title,
                        status: $task->status->value,
                        secondsTracked: $secondsTracked,
                    )
                    : null,
            );
        }

        return $tasks;
    }

    /**
     * @return list<ReportPreviewDayTask>
     */
    private function findTasksForDay(ReportDay $reportDay): array
    {
        $tasks = [];

        foreach ($reportDay->reportDayTasks as $rdt) {
            $reportTask = $rdt->reportTask;

            if ($reportTask === null) {
                continue;
            }

            $bitrix24TaskId = $reportTask->task?->bitrix24_task_id;
            $rawTitle = $reportTask->task?->title;
            $displayTitle = $rawTitle !== null && $rawTitle !== ''
                ? $rawTitle
                : ($bitrix24TaskId !== null ? "#{$bitrix24TaskId} (без названия)" : '');

            $tasks[] = new ReportPreviewDayTask(
                id: $reportTask->task_id,
                title: $displayTitle,
                projectName: $reportTask->project_name,
                narrative: $rdt->narrative ?? $reportTask->narrative,
                isEdited: $rdt->is_edited,
            );
        }

        if ($tasks !== []) {
            return $tasks;
        }

        return $this->buildFallbackTaskFromNarrative($reportDay);
    }

    /**
     * @return list<ReportPreviewDayTask>
     */
    private function buildFallbackTaskFromNarrative(ReportDay $reportDay): array
    {
        if ($reportDay->narrative === null || $reportDay->narrative === '') {
            return [];
        }

        return [
            new ReportPreviewDayTask(
                id: null,
                title: 'Прочие работы',
                projectName: null,
                narrative: $reportDay->narrative,
                isEdited: $reportDay->is_edited,
            ),
        ];
    }
}

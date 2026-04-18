<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Report\DTOs\ReportExportMonthlyDay;
use App\Domain\Report\DTOs\ReportExportMonthlyTask;
use App\Domain\Report\DTOs\ReportExportTask;
use App\Domain\Report\Models\Report;

final readonly class GetMonthlyReportData
{
    public function __construct(
        private GetTaskTimeBreakdown $getTaskTimeBreakdown,
    ) {
    }

    /**
     * Build monthly report days with tasks enriched by Bitrix24 metadata
     * (status, bitrix24_url, secondsTracked) for rendering in monthly Word export.
     *
     * @return list<ReportExportMonthlyDay>
     */
    public function __invoke(Report $report): array
    {
        $report->load(['reportDays.reportDayTasks.reportTask.task']);

        $breakdown = $this->getTaskTimeBreakdown->__invoke($report->getDateRange());

        $days = [];

        foreach ($report->reportDays as $reportDay) {
            $tasks = [];

            foreach ($reportDay->reportDayTasks as $rdt) {
                $reportTask = $rdt->reportTask;

                if ($reportTask === null) {
                    continue;
                }

                $task = $reportTask->task;
                $bitrix24TaskId = $task !== null ? $task->bitrix24_task_id : null;
                $secondsTracked = ($bitrix24TaskId !== null && isset($breakdown[$bitrix24TaskId]))
                    ? $breakdown[$bitrix24TaskId]
                    : null;

                $rawTitle = $task !== null ? $task->title : null;
                $displayTitle = $rawTitle !== null && $rawTitle !== ''
                    ? $rawTitle
                    : ($bitrix24TaskId !== null ? "#{$bitrix24TaskId} (без названия)" : '');

                $base = new ReportExportTask(
                    title: $displayTitle,
                    projectName: $reportTask->project_name ?? '',
                    narrative: $rdt->narrative ?? $reportTask->narrative ?? '',
                    secondsTracked: $secondsTracked,
                );

                $tasks[] = $task !== null
                    ? new ReportExportMonthlyTask(
                        base: $base,
                        id: $task->bitrix24_task_id,
                        status: $task->status->value,
                        bitrix24Link: $task->bitrix24_url,
                    )
                    : new ReportExportMonthlyTask(base: $base);
            }

            $days[] = new ReportExportMonthlyDay(
                date: $reportDay->date->format('Y-m-d'),
                tasks: $tasks,
                narrative: $reportDay->narrative,
            );
        }

        return $days;
    }
}

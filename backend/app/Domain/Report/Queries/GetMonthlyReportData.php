<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Report\DTOs\ReportExportMonthlyDay;
use App\Domain\Report\DTOs\ReportExportMonthlyTask;
use App\Domain\Report\DTOs\ReportExportTask;
use App\Domain\Report\Models\Report;

final readonly class GetMonthlyReportData
{
    /**
     * Build monthly report days with tasks enriched by Bitrix24 metadata
     * (status, bitrix24_url) for rendering in monthly Word export.
     *
     * @return list<ReportExportMonthlyDay>
     */
    public function __invoke(Report $report): array
    {
        $report->load(['reportDays.reportDayTasks.reportTask.task']);

        $days = [];

        foreach ($report->reportDays as $reportDay) {
            $tasks = [];

            foreach ($reportDay->reportDayTasks as $rdt) {
                $reportTask = $rdt->reportTask;

                if ($reportTask === null) {
                    continue;
                }

                $task = $reportTask->task;
                $base = new ReportExportTask(
                    title: $task !== null ? $task->title : '',
                    projectName: $reportTask->project_name ?? '',
                    narrative: $rdt->narrative ?? $reportTask->narrative ?? '',
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

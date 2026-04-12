<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportTask;

final readonly class GetReportPreview
{
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
    public function __invoke(Report $report): array
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
     * @return list<array{id: int|null, title: string, project_name: string|null, narrative: string|null, is_edited: bool}>
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

<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\ReportDaySource;
use App\Enums\ReportType;
use App\Models\Commit;
use App\Models\MatchResult;
use App\Models\Report;
use App\Models\ReportDay;
use App\Models\ReportTask;
use App\Models\Setting;

class PromptExportService implements PromptExportServiceInterface
{
    private const array REPORT_TYPE_LABELS = [
        'daily'   => 'Ежедневный',
        'weekly'  => 'Еженедельный',
        'monthly' => 'Ежемесячный',
        'custom'  => 'Произвольный',
    ];

    private const array DAY_OF_WEEK_LABELS = [
        'Monday'    => 'Понедельник',
        'Tuesday'   => 'Вторник',
        'Wednesday' => 'Среда',
        'Thursday'  => 'Четверг',
        'Friday'    => 'Пятница',
        'Saturday'  => 'Суббота',
        'Sunday'    => 'Воскресенье',
    ];
    private bool $enrichmentEnabled = true;

    public function buildPromptFile(Report $report): string
    {
        $report->load(['reportTasks.task.matchResults.branch', 'reportDays']);

        $settings = Setting::first();
        /** @var string $defaultPrompt */
        $defaultPrompt = config('llm.default_system_prompt');
        $systemPrompt = $settings !== null
            ? ($settings->llm_system_prompt ?? $defaultPrompt)
            : $defaultPrompt;
        $developerName = $settings !== null ? ($settings->developer_name ?? 'Разработчик') : 'Разработчик';
        $developerPosition = $settings !== null ? ($settings->developer_position ?? '') : '';

        /** @var list<string> $lines */
        $lines = [];
        $lines[] = '=== ИНСТРУКЦИЯ ДЛЯ ГЕНЕРАЦИИ ОТЧЁТА ===';
        $lines[] = '';
        $lines[] = 'Ниже приведены данные для генерации отчёта о проделанной работе.';
        $lines[] = 'Сгенерируй нарративное описание (2-3 предложения на русском языке)';
        $lines[] = 'для каждой задачи на основе списка коммитов.';
        $lines[] = '';
        $lines[] = '--- СИСТЕМНЫЙ ПРОМПТ ---';
        $lines[] = '';
        $lines[] = $systemPrompt;
        $lines[] = '';
        $lines[] = '--- СВОДКА ОТЧЁТА ---';
        $lines[] = '';
        $lines[] = 'Разработчик: ' . $developerName;
        $lines[] = 'Должность: ' . $developerPosition;
        $lines[] = 'Период: ' . $report->date_from->format('Y-m-d') . ' — ' . $report->date_to->format('Y-m-d');
        $lines[] = 'Тип отчёта: ' . $this->translateReportType($report->type);
        $lines[] = '';
        $lines[] = '--- ЗАДАЧИ ---';

        $this->enrichmentEnabled = $settings !== null ? ($settings->enriched_prompt_enabled ?? true) : true;

        foreach ($report->reportTasks as $reportTask) {
            $lines[] = '';
            $this->appendTaskSection($lines, $reportTask);
            $lines[] = '';
            $lines[] = '---';
        }

        $fallbackDays = $report->reportDays->filter(
            fn (ReportDay $day): bool => $day->source === ReportDaySource::Bitrix24Fallback,
        );

        if ($fallbackDays->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '--- ДНИ БЕЗ КОММИТОВ ---';

            foreach ($fallbackDays as $reportDay) {
                $lines[] = '';
                $this->appendDaySection($lines, $reportDay, $report);
                $lines[] = '';
                $lines[] = '---';
            }
        }

        $lines[] = '';
        $lines[] = '=== КОНЕЦ ДАННЫХ ===';
        $lines[] = '';
        $lines[] = 'Пожалуйста, сгенерируй нарративное описание для каждой задачи';
        $lines[] = 'и для каждого дня без коммитов, следуя инструкциям из системного промпта.';
        $lines[] = 'Формат ответа: для каждой задачи/дня — 2-3 предложения на русском языке';
        $lines[] = 'в деловом стиле.';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendTaskSection(array &$lines, ReportTask $reportTask): void
    {
        $task = $reportTask->task;
        $taskId = $task !== null ? ($task->bitrix24_task_id ?? $reportTask->task_id ?? 0) : ($reportTask->task_id ?? 0);
        $title = $task !== null ? $task->title : 'Без названия';
        $projectName = $reportTask->project_name ?? ($task !== null ? $task->project_name : null) ?? 'Не указан';
        $status = $task !== null ? $task->status->value : 'unknown';

        $lines[] = '### Задача #' . $taskId . ': ' . $title;
        $lines[] = 'Проект: ' . $projectName;
        $lines[] = 'Статус: ' . $status;

        $commitMessages = $this->findCommitMessagesForTask($reportTask);

        $lines[] = 'Коммиты:';
        if ($commitMessages === []) {
            $lines[] = '  (нет коммитов)';
        } else {
            foreach ($commitMessages as $message) {
                $lines[] = '  - ' . $message;
            }
        }

        if ($this->enrichmentEnabled && $task !== null) {
            $task->loadMissing('matchResults.branch');

            foreach ($task->matchResults as $matchResult) {
                $branch = $matchResult->branch;
                if ($branch === null) {
                    continue;
                }

                if ($branch->mr_title !== null) {
                    $lines[] = 'Название MR: ' . $branch->mr_title;
                }
                if ($branch->mr_description !== null) {
                    $lines[] = 'Описание MR: ' . $branch->mr_description;
                }
                if ($branch->mr_additions !== null) {
                    $lines[] = 'Статистика: +' . $branch->mr_additions . ' / -' . ($branch->mr_deletions ?? 0) . ' строк';
                }
                if (is_array($branch->mr_changed_files) && $branch->mr_changed_files !== []) {
                    $files = array_slice($branch->mr_changed_files, 0, 20);
                    $lines[] = 'Изменённые файлы: ' . implode(', ', $files);
                }
                break;
            }
        }

        $narrative = $reportTask->narrative ?? 'Не сгенерирован';
        $lines[] = '';
        $lines[] = 'Текущий нарратив (если есть): ' . $narrative;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendDaySection(array &$lines, ReportDay $reportDay, Report $report): void
    {
        $dateString = $reportDay->date->format('Y-m-d');
        $dayOfWeek = self::DAY_OF_WEEK_LABELS[$reportDay->date->format('l')] ?? $reportDay->date->format('l');

        $lines[] = '### ' . $dateString . ' (' . $dayOfWeek . ')';
        $lines[] = 'Активные задачи в этот день:';

        $hasActiveTasks = false;

        foreach ($report->reportTasks as $reportTask) {
            if ($reportTask->task !== null) {
                $taskId = $reportTask->task->bitrix24_task_id ?? $reportTask->task_id;
                $title = $reportTask->task->title;
                $status = $reportTask->task->status->value;
                $lines[] = '  - #' . $taskId . ': ' . $title . ' (статус: ' . $status . ')';
                $hasActiveTasks = true;
            }
        }

        if (! $hasActiveTasks) {
            $lines[] = '  (нет активных задач)';
        }

        $narrative = $reportDay->narrative ?? 'Не сгенерирован';
        $lines[] = '';
        $lines[] = 'Текущий нарратив (если есть): ' . $narrative;
    }

    /**
     * Find commit messages for a task through task -> matchResults -> branches -> commits.
     *
     * @return list<string>
     */
    private function findCommitMessagesForTask(ReportTask $reportTask): array
    {
        if ($reportTask->task_id === null) {
            return [];
        }

        /** @var list<int> $branchIds */
        $branchIds = MatchResult::where('task_id', $reportTask->task_id)
            ->distinct()
            ->pluck('branch_id')
            ->all();

        if ($branchIds === []) {
            return [];
        }

        /** @var list<string> */
        return Commit::whereIn('branch_id', $branchIds)
            ->orderBy('committed_at')
            ->pluck('message')
            ->all();
    }

    private function translateReportType(ReportType $type): string
    {
        return self::REPORT_TYPE_LABELS[$type->value];
    }
}

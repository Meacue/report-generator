<?php

declare(strict_types=1);

namespace App\Infrastructure\Report;

use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportType;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\Queries\GetTaskTimeTimeline;
use App\Domain\Report\Services\PromptExportServiceInterface;
use App\Domain\Settings\Models\Setting;

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

    public function __construct(
        private readonly GetTaskTimeTimeline $getTaskTimeTimeline,
    ) {
    }

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

        $period = $report->getDateRange();
        $timeline = $this->getTaskTimeTimeline->__invoke($period);

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
            $this->appendTaskSection($lines, $reportTask, $timeline);
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

        $this->appendChronologySection($lines, $timeline);

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
     * Format seconds as h:mm:ss (no leading zero on hours).
     *
     * Examples:
     *   330   => "0:05:30"
     *   9015  => "2:30:15"
     *   1036800 => "288:00:00"
     */
    public function formatHms(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, array<int, int>>  $timeline
     */
    private function appendTaskSection(array &$lines, ReportTask $reportTask, array $timeline): void
    {
        $task = $reportTask->task;
        $taskId = $task !== null ? ($task->bitrix24_task_id ?? $reportTask->task_id ?? 0) : ($reportTask->task_id ?? 0);
        $title = $task !== null ? ($task->title ?? "#{$taskId} (без названия)") : 'Без названия';
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

        $this->appendEnrichmentData($lines, $reportTask);

        // Sum seconds for this task across all days in the timeline.
        $bitrix24TaskId = $task !== null ? $task->bitrix24_task_id : null;
        if ($bitrix24TaskId !== null) {
            $totalSeconds = 0;
            foreach ($timeline as $dayTasks) {
                if (isset($dayTasks[$bitrix24TaskId])) {
                    $totalSeconds += $dayTasks[$bitrix24TaskId];
                }
            }

            if ($totalSeconds > 0) {
                $lines[] = 'Время по задаче (суммарно за период): ' . $this->formatHms($totalSeconds);
            }
        }

        $narrative = $reportTask->narrative ?? 'Не сгенерирован';
        $lines[] = '';
        $lines[] = 'Текущий нарратив (если есть): ' . $narrative;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendEnrichmentData(array &$lines, ReportTask $reportTask): void
    {
        if (! $this->enrichmentEnabled) {
            return;
        }

        $task = $reportTask->task;
        if ($task === null) {
            return;
        }

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
     * Append the "Хронология дня" block if there is any timeline data.
     *
     * @param  array<int, string>  $lines
     * @param  array<string, array<int, int>>  $timeline  map "Y-m-d" => [task_id => seconds]
     */
    private function appendChronologySection(array &$lines, array $timeline): void
    {
        if ($timeline === []) {
            return;
        }

        $lines[] = '';
        $lines[] = '--- ХРОНОЛОГИЯ ДНЯ ---';

        foreach ($timeline as $ymd => $tasks) {
            // Convert Y-m-d to d.m.Y for display.
            $parts = explode('-', $ymd);
            $displayDate = $parts[2] . '.' . $parts[1] . '.' . $parts[0];

            $lines[] = '';
            $lines[] = $displayDate . ':';

            foreach ($tasks as $taskId => $seconds) {
                $lines[] = '  #' . $taskId . ' — ' . $this->formatHms($seconds);
            }
        }
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

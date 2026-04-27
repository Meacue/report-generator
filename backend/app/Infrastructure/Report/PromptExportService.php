<?php

declare(strict_types=1);

namespace App\Infrastructure\Report;

use App\Domain\Report\DTOs\PromptExportDay;
use App\Domain\Report\DTOs\PromptExportDayTask;
use App\Domain\Report\DTOs\PromptExportPeriodTask;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportType;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Services\PromptExportDataAssembler;
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

    public function __construct(
        private readonly PromptExportDataAssembler $assembler,
    ) {
    }

    public function buildPromptFile(Report $report): string
    {
        $settings = Setting::first();
        /** @var string $defaultPrompt */
        $defaultPrompt = config('llm.default_system_prompt');
        $systemPrompt = $settings !== null
            ? ($settings->llm_system_prompt ?? $defaultPrompt)
            : $defaultPrompt;
        $developerName = $settings !== null ? ($settings->developer_name ?? 'Разработчик') : 'Разработчик';
        $developerPosition = $settings !== null ? ($settings->developer_position ?? '') : '';

        $data = $this->assembler->assemble($report);

        /** @var list<string> $lines */
        $lines = [];
        $lines[] = '=== ИНСТРУКЦИЯ ДЛЯ ГЕНЕРАЦИИ ОТЧЁТА ===';
        $lines[] = '';
        $lines[] = '--- СИСТЕМНЫЙ ПРОМПТ ---';
        $lines[] = '';
        $lines[] = $systemPrompt;
        $lines[] = '';
        $lines[] = '--- СВОДКА ОТЧЁТА ---';
        $lines[] = '';
        $lines[] = 'Должность разработчика: ' . $developerPosition;
        $lines[] = 'Период: ' . $report->date_from->format('Y-m-d') . ' — ' . $report->date_to->format('Y-m-d');
        $lines[] = 'Тип отчёта: ' . $this->translateReportType($report->type);
        $lines[] = '';

        foreach ($this->formatPeriodTasksSection($data->periodTasks) as $line) {
            $lines[] = $line;
        }

        $lines[] = '';

        foreach ($this->formatDaysSection($data->days) as $line) {
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = '=== КОНЕЦ ДАННЫХ ===';
        $lines[] = '';
        $lines[] = 'Пожалуйста, сгенерируй нарративное описание для каждой задачи и для каждого дня,';
        $lines[] = 'следуя инструкциям из системного промпта. Формат ответа: для каждой задачи/дня —';
        $lines[] = '2-3 предложения на русском языке в деловом стиле.';

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
     * @param  list<PromptExportPeriodTask>  $tasks
     * @return list<string>
     */
    private function formatPeriodTasksSection(array $tasks): array
    {
        /** @var list<string> $lines */
        $lines = [];
        $lines[] = '--- ЗАДАЧИ ПЕРИОДА ---';
        $lines[] = '';

        if ($tasks === []) {
            $lines[] = '(нет задач)';

            return $lines;
        }

        foreach ($tasks as $task) {
            $lines[] = $this->formatPeriodTaskLine($task);
        }

        return $lines;
    }

    private function formatPeriodTaskLine(PromptExportPeriodTask $task): string
    {
        $line = '### #' . $task->bitrix24TaskId;

        if ($task->projectName !== null && $task->projectName !== '') {
            $line .= ' (' . $task->projectName . ')';
        }

        $line .= ': ' . $task->title;

        /** @var list<string> $tail */
        $tail = [];
        if ($task->status !== null) {
            $tail[] = 'статус ' . $task->status;
        }
        if ($task->totalSeconds > 0) {
            $tail[] = $this->formatHms($task->totalSeconds);
        }

        if ($tail !== []) {
            $line .= ' — ' . implode(', ', $tail);
        }

        return $line;
    }

    /**
     * @param  list<PromptExportDay>  $days
     * @return list<string>
     */
    private function formatDaysSection(array $days): array
    {
        /** @var list<string> $lines */
        $lines = [];
        $lines[] = '--- ДНИ ---';
        $lines[] = '';

        foreach ($days as $index => $day) {
            foreach ($this->formatDay($day) as $line) {
                $lines[] = $line;
            }

            if ($index !== array_key_last($days)) {
                $lines[] = '';
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function formatDay(PromptExportDay $day): array
    {
        /** @var list<string> $lines */
        $lines = [];

        $sourceLabel = $day->isEmpty
            ? 'нет активности'
            : $this->translateSource($day->source);

        $lines[] = '### ' . $day->date . ' (' . $day->dayOfWeek . ') — источник: ' . $sourceLabel;

        if ($day->isEmpty) {
            $lines[] = 'Задачи дня: (нет активности)';

            return $lines;
        }

        $lines[] = 'Задачи дня:';
        foreach ($day->tasks as $task) {
            foreach ($this->formatDayTask($task) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function formatDayTask(PromptExportDayTask $task): array
    {
        /** @var list<string> $lines */
        $lines = [];

        $header = '  #' . $task->bitrix24TaskId . ': ' . $task->title;
        if ($task->seconds > 0) {
            $header .= ' — ' . $this->formatHms($task->seconds);
        }
        $lines[] = $header;

        if ($task->commits === []) {
            $lines[] = '    Коммиты: (нет за этот день)';
        } else {
            $lines[] = '    Коммиты:';
            foreach ($task->commits as $message) {
                $lines[] = '      - ' . $message;
            }
        }

        if ($task->narrative !== null && $task->narrative !== '') {
            $lines[] = '    Нарратив (день): ' . $task->narrative;
        }

        return $lines;
    }

    private function translateSource(?ReportDaySource $source): string
    {
        return match ($source) {
            ReportDaySource::Commits          => 'коммиты',
            ReportDaySource::Bitrix24Fallback => 'Bitrix24-fallback',
            ReportDaySource::Manual           => 'ручной ввод',
            null                              => 'источник не определён',
        };
    }

    private function translateReportType(ReportType $type): string
    {
        return self::REPORT_TYPE_LABELS[$type->value];
    }
}

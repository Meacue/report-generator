<?php

declare(strict_types=1);

namespace App\Services\Report;

use PhpOffice\PhpWord\ComplexType\TblWidth as ComplexTblWidth;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use App\Domain\Report\Services\ReportExporterInterface;
use RuntimeException;

class WordExporter implements ReportExporterInterface
{
    private const FONT_MAIN = 'Times New Roman';

    private const FONT_DEFAULT = 'Arial';

    private const SIZE_MAIN = 24; // half-points: 12pt

    private const SIZE_HEADING1 = 28; // half-points: 14pt

    private const SIZE_HEADING2 = 24; // half-points: 12pt

    private const SIZE_TABLE = 20; // half-points: 10pt

    /** Page margins in twips (2.54cm = 1440 twips) */
    private const MARGIN_WEEKLY = 1440;

    /** Page margins in twips (2cm = 1134 twips) */
    private const MARGIN_TOP = 1134;

    private const MARGIN_BOTTOM = 1134;

    private const MARGIN_LEFT = 1134;

    /** Page right margin in twips (1cm = 567 twips) */
    private const MARGIN_RIGHT = 567;

    /** First-line indent in twips (1.25cm ≈ 709 twips) */
    private const INDENT_FIRST_LINE = 709;

    /** Table width in percent */
    private const TABLE_WIDTH = 100;

    /** Weekly template table width in twips */
    private const WEEKLY_TABLE_WIDTH = 9735;

    /** Weekly template table indent in twips */
    private const WEEKLY_TABLE_INDENT = 720;

    /** Weekly border size in eighths of a point (8 = 1pt) */
    private const WEEKLY_BORDER_SIZE = 8;

    /** A4 page width in twips */
    private const PAGE_WIDTH_A4 = 11909;

    /** A4 page height in twips */
    private const PAGE_HEIGHT_A4 = 16834;

    /**
     * Export report to .docx file.
     *
     * @param  array<string, mixed>  $reportData
     * @return string Path to generated file
     */
    public function export(array $reportData): string
    {
        $type = $this->str($reportData['type'] ?? 'custom');

        return match ($type) {
            'weekly'  => $this->exportWeekly($reportData),
            'monthly' => $this->exportMonthly($reportData),
            default   => $this->exportDefault($reportData),
        };
    }

    /**
     * Export weekly report matching report-blank.docx template exactly.
     *
     * @param  array<string, mixed>  $reportData
     */
    private function exportWeekly(array $reportData): string
    {
        $phpWord = new PhpWord();

        $phpWord->setDefaultFontName(self::FONT_DEFAULT);
        $phpWord->setDefaultFontSize(11);

        $language = new Language(Language::RU_RU);
        $phpWord->getSettings()->setThemeFontLang($language);

        $section = $phpWord->addSection([
            'pageSizeW'    => self::PAGE_WIDTH_A4,
            'pageSizeH'    => self::PAGE_HEIGHT_A4,
            'marginTop'    => self::MARGIN_WEEKLY,
            'marginBottom' => self::MARGIN_WEEKLY,
            'marginLeft'   => self::MARGIN_WEEKLY,
            'marginRight'  => self::MARGIN_WEEKLY,
        ]);

        $section->addText(
            'Приложение 1. Форма еженедельного отчета',
            ['name'      => self::FONT_MAIN, 'size' => 12],
            ['alignment' => Jc::RIGHT]
        );

        $section->addTextBreak();

        $developerName = $this->str($reportData['developer_name'] ?? '_______________');
        $developerPosition = $this->str($reportData['developer_position'] ?? '_______________');
        $dateFrom = $this->str($reportData['date_from'] ?? '___________');
        $dateTo = $this->str($reportData['date_to'] ?? '___________');

        /** @var array<int, mixed> $rawDays */
        $rawDays = is_array($reportData['days'] ?? null) ? $reportData['days'] : [];

        $tableStyle = [
            'borderSize'  => self::WEEKLY_BORDER_SIZE,
            'borderColor' => '000000',
            'width'       => self::WEEKLY_TABLE_WIDTH,
            'unit'        => TblWidth::TWIP,
            'indent'      => new ComplexTblWidth(self::WEEKLY_TABLE_INDENT, TblWidth::TWIP),
        ];

        $fontStyle = ['name' => self::FONT_MAIN, 'size' => 12];
        $fontBold = ['name' => self::FONT_MAIN, 'size' => 12, 'bold' => true];
        $paragraphStyle = ['lineHeight' => 1.0, 'spaceAfter' => 0, 'spaceBefore' => 0];

        $table = $section->addTable($tableStyle);

        $this->addWeeklyEmployeeHeaderRow($table, $developerName, $developerPosition, $dateFrom, $dateTo, $fontStyle, $paragraphStyle);
        $this->addWeeklyDayRows($table, $rawDays, $fontStyle, $fontBold, $paragraphStyle);

        return $this->saveDocument($phpWord, $reportData);
    }

    /**
     * @param  array<string, mixed>  $fontStyle
     * @param  array<string, mixed>  $paragraphStyle
     */
    private function addWeeklyEmployeeHeaderRow(
        Table $table,
        string $developerName,
        string $developerPosition,
        string $dateFrom,
        string $dateTo,
        array $fontStyle,
        array $paragraphStyle,
    ): void {
        $table->addRow();
        $headerCell = $table->addCell(self::WEEKLY_TABLE_WIDTH, ['valign' => 'top']);

        $this->addTextToCell($headerCell, "ФИО сотрудника: {$developerName}", $fontStyle, $paragraphStyle);
        $this->addTextToCell($headerCell, "Должность: {$developerPosition}", $fontStyle, $paragraphStyle);
        $this->addTextToCell($headerCell, "Даты удалённой работы: с {$dateFrom} по {$dateTo}", $fontStyle, $paragraphStyle);
        $this->addTextToCell($headerCell, 'Список выполненных работ в свободной форме:', $fontStyle, $paragraphStyle);
    }

    /**
     * @param  array<int, mixed>  $rawDays
     * @param  array<string, mixed>  $fontStyle
     * @param  array<string, mixed>  $fontBold
     * @param  array<string, mixed>  $paragraphStyle
     */
    private function addWeeklyDayRows(
        Table $table,
        array $rawDays,
        array $fontStyle,
        array $fontBold,
        array $paragraphStyle,
    ): void {
        $russianDays = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница'];

        for ($i = 0; $i < 5; $i++) {
            $table->addRow();
            $dayCell = $table->addCell(self::WEEKLY_TABLE_WIDTH, ['valign' => 'top']);

            $rawDay = $rawDays[$i] ?? null;

            /** @var array<string, mixed>|null $dayData */
            $dayData = is_array($rawDay) ? $rawDay : null;

            if ($dayData !== null) {
                $dayName = $russianDays[$i];
                $date = $this->str($dayData['date'] ?? '');
                $formattedDate = $this->formatDateShort($date);
                $this->addTextToCell($dayCell, "{$dayName}, {$formattedDate}:", $fontBold, $paragraphStyle);

                /** @var array<int, mixed> $rawTasks */
                $rawTasks = is_array($dayData['tasks'] ?? null) ? $dayData['tasks'] : [];

                foreach ($rawTasks as $rawTask) {
                    /** @var array<string, mixed> $task */
                    $task = is_array($rawTask) ? $rawTask : [];
                    $taskTitle = $this->str($task['title'] ?? '');
                    $taskNarrative = $this->str($task['narrative'] ?? '');

                    if ($taskTitle !== '') {
                        $this->addTextToCell($dayCell, "• {$taskTitle}", $fontBold, $paragraphStyle);
                    }

                    if ($taskNarrative !== '') {
                        $this->addTextToCell($dayCell, "  {$taskNarrative}", $fontStyle, $paragraphStyle);
                    }
                }
            } else {
                $this->addEmptyDayCell($dayCell, $fontStyle, $paragraphStyle);
            }
        }
    }

    /**
     * Fill empty day cell with 4 blank paragraphs to match the weekly template layout.
     *
     * @param  array<string, mixed>  $fontStyle
     * @param  array<string, mixed>  $paragraphStyle
     */
    private function addEmptyDayCell(Cell $cell, array $fontStyle, array $paragraphStyle): void
    {
        $this->addTextToCell($cell, '', $fontStyle, $paragraphStyle);
        $this->addTextToCell($cell, '', $fontStyle, $paragraphStyle);
        $this->addTextToCell($cell, '', $fontStyle, $paragraphStyle);
        $this->addTextToCell($cell, '', $fontStyle, $paragraphStyle);
    }

    /**
     * Export monthly report grouped by project (PRD section 9.2).
     *
     * @param  array<string, mixed>  $reportData
     */
    private function exportMonthly(array $reportData): string
    {
        $phpWord = $this->createPhpWord();
        $section = $this->createSection($phpWord);

        $developerName = $this->str($reportData['developer_name'] ?? 'Разработчик');
        $developerPosition = $this->str($reportData['developer_position'] ?? '');
        $dateFrom = $this->str($reportData['date_from'] ?? '');
        $dateTo = $this->str($reportData['date_to'] ?? '');

        /** @var array<int, mixed> $rawDays */
        $rawDays = is_array($reportData['days'] ?? null) ? $reportData['days'] : [];

        $this->addMonthlyHeader($section, $developerName, $developerPosition, $dateFrom, $dateTo);

        $grouped = $this->groupTasksByProject($rawDays);
        $this->addStatsLine($section, $grouped['totalTasks'], count($rawDays));
        $this->addProjectSections($section, $grouped['tasksByProject']);

        /** @var array<int, mixed> $rawUnclassified */
        $rawUnclassified = is_array($reportData['unclassified_commits'] ?? null)
            ? $reportData['unclassified_commits']
            : [];

        $this->addUnclassifiedCommitsSection($section, $rawUnclassified);

        return $this->saveDocument($phpWord, $reportData);
    }

    /**
     * @see docs/PRD-v2.md section 9.1
     */
    private function addMonthlyHeader(
        Section $section,
        string $developerName,
        string $developerPosition,
        string $dateFrom,
        string $dateTo,
    ): void {
        $titleRun = $section->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $titleRun->addText(
            'Отчёт о работе — ' . $developerName,
            ['name' => self::FONT_MAIN, 'size' => self::SIZE_HEADING1 / 2, 'bold' => true]
        );

        $periodRun = $section->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $periodRun->addText(
            'Период: ' . $dateFrom . ' — ' . $dateTo,
            ['name' => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2]
        );

        if ($developerPosition !== '') {
            $positionRun = $section->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
            $positionRun->addText(
                'Должность: ' . $developerPosition,
                ['name' => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2]
            );
        }
    }

    /**
     * @param  array<int, mixed>  $rawDays
     * @return array{tasksByProject: array<string, array<int, array<string, mixed>>>, totalTasks: int}
     */
    private function groupTasksByProject(array $rawDays): array
    {
        /** @var array<string, array<int, array<string, mixed>>> $tasksByProject */
        $tasksByProject = [];
        $totalTasks = 0;

        foreach ($rawDays as $rawDay) {
            /** @var array<string, mixed> $day */
            $day = is_array($rawDay) ? $rawDay : [];

            /** @var array<int, mixed> $rawTasks */
            $rawTasks = is_array($day['tasks'] ?? null) ? $day['tasks'] : [];

            foreach ($rawTasks as $rawTask) {
                /** @var array<string, mixed> $task */
                $task = is_array($rawTask) ? $rawTask : [];

                $projectNameRaw = $task['project_name'] ?? null;
                $projectName = is_string($projectNameRaw) && $projectNameRaw !== ''
                    ? $projectNameRaw
                    : 'Без проекта';

                $taskTitle = $this->str($task['title'] ?? '');
                $alreadyAdded = false;

                if (isset($tasksByProject[$projectName])) {
                    foreach ($tasksByProject[$projectName] as $existing) {
                        if ($this->str($existing['title'] ?? '') === $taskTitle) {
                            $alreadyAdded = true;
                            break;
                        }
                    }
                }

                if (! $alreadyAdded) {
                    $tasksByProject[$projectName][] = $task;
                    $totalTasks++;
                }
            }
        }

        return ['tasksByProject' => $tasksByProject, 'totalTasks' => $totalTasks];
    }

    private function addStatsLine(Section $section, int $totalTasks, int $workDays): void
    {
        $statsRun = $section->addTextRun(['spaceAfter' => 240]);
        $statsRun->addText(
            'Всего задач: ' . $totalTasks . '   |   Рабочих дней: ' . $workDays,
            ['name' => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2]
        );
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tasksByProject
     *
     * @see docs/PRD-v2.md section 9.2
     */
    private function addProjectSections(Section $section, array $tasksByProject): void
    {
        foreach ($tasksByProject as $projectName => $projectTasks) {
            $this->addSeparatorLine($section);

            $section->addText(
                'Проект: ' . $projectName,
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_HEADING2 / 2, 'bold' => true],
                ['spaceAfter' => 0]
            );

            $this->addSeparatorLine($section, spaceBefore: 0, spaceAfter: 120);

            $this->addTasksByStatus($section, $projectTasks, 'Завершённые задачи:', 'Нет завершённых задач.', completed: true);
            $this->addTasksByStatus($section, $projectTasks, 'В работе:', 'Нет задач в работе.', completed: false);
        }
    }

    private function addSeparatorLine(Section $section, int $spaceBefore = 240, int $spaceAfter = 0): void
    {
        $section->addText(
            str_repeat('─', 45),
            ['name'        => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
            ['spaceBefore' => $spaceBefore, 'spaceAfter' => $spaceAfter]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    private function addTasksByStatus(
        Section $section,
        array $tasks,
        string $heading,
        string $emptyMessage,
        bool $completed,
    ): void {
        $section->addText(
            $heading,
            ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2, 'bold' => true],
            ['spaceAfter' => 60]
        );

        $hasEntries = false;

        foreach ($tasks as $task) {
            $isCompleted = $this->str($task['status'] ?? '') === 'completed';

            if ($completed !== $isCompleted) {
                continue;
            }

            $hasEntries = true;
            $this->addMonthlyTaskEntry($section, $task);
        }

        if (! $hasEntries) {
            $section->addText(
                '  ' . $emptyMessage,
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2, 'italic' => true],
                ['spaceAfter' => 60, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }
    }

    /**
     * @param  array<int, mixed>  $rawUnclassified
     *
     * @see docs/PRD-v2.md section 9.3
     */
    private function addUnclassifiedCommitsSection(Section $section, array $rawUnclassified): void
    {
        if ($rawUnclassified === []) {
            return;
        }

        $this->addSeparatorLine($section);

        $section->addText(
            'Неклассифицированные коммиты',
            ['name'       => self::FONT_MAIN, 'size' => self::SIZE_HEADING2 / 2, 'bold' => true],
            ['spaceAfter' => 0]
        );

        $this->addSeparatorLine($section, spaceBefore: 0, spaceAfter: 120);

        foreach ($rawUnclassified as $rawCommit) {
            /** @var array<string, mixed> $commit */
            $commit = is_array($rawCommit) ? $rawCommit : [];
            $repo = $this->str($commit['repo'] ?? '');
            $message = $this->str($commit['message'] ?? '');
            $branch = $this->str($commit['branch'] ?? '');

            $line = "• [repo: {$repo}] {$message}";

            if ($branch !== '') {
                $line .= " (branch: {$branch})";
            }

            $section->addText(
                $line,
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
                ['spaceAfter' => 60, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }
    }

    /**
     * Add a single task entry in monthly report format (PRD 9.2).
     *
     * @param  array<string, mixed>  $task
     */
    private function addMonthlyTaskEntry(Section $section, array $task): void
    {
        $taskIdRaw = $task['id'] ?? null;
        $taskId = $taskIdRaw !== null ? $this->str($taskIdRaw) : '';
        $title = $this->str($task['title'] ?? '');
        $narrative = $this->str($task['narrative'] ?? '');
        $link = $this->str($task['bitrix24_link'] ?? '');

        $idPrefix = $taskId !== '' ? "Задача #{$taskId} — " : '';

        $section->addText(
            "  • {$idPrefix}{$title}",
            ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2, 'bold' => true],
            ['spaceAfter' => 0, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
        );

        if ($link !== '') {
            $section->addText(
                "    {$link}",
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
                ['spaceAfter' => 0, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }

        if ($narrative !== '') {
            $section->addText(
                "    {$narrative}",
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
                ['spaceAfter' => 60, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }
    }

    /**
     * Export default report (daily / custom) — original format with task tables.
     *
     * @param  array<string, mixed>  $reportData
     */
    private function exportDefault(array $reportData): string
    {
        $phpWord = $this->createPhpWord();
        $section = $this->createSection($phpWord);

        $this->addHeader($section, $reportData);
        $this->addDays($section, $reportData);
        $this->addSummary($section, $reportData);

        return $this->saveDocument($phpWord, $reportData);
    }

    private function createPhpWord(): PhpWord
    {
        $phpWord = new PhpWord();

        $phpWord->setDefaultFontName(self::FONT_MAIN);
        $phpWord->setDefaultFontSize(self::SIZE_MAIN / 2);

        $language = new Language(Language::RU_RU);
        $phpWord->setDefaultParagraphStyle([
            'spaceAfter'  => 120,
            'spaceBefore' => 0,
        ]);

        $phpWord->getSettings()->setThemeFontLang($language);

        return $phpWord;
    }

    private function createSection(PhpWord $phpWord): Section
    {
        return $phpWord->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => self::MARGIN_TOP,
            'marginBottom' => self::MARGIN_BOTTOM,
            'marginLeft'   => self::MARGIN_LEFT,
            'marginRight'  => self::MARGIN_RIGHT,
        ]);
    }

    /**
     * @param  array<string, mixed>  $reportData
     */
    private function addHeader(Section $section, array $reportData): void
    {
        $developerName = $this->str($reportData['developer_name'] ?? '');
        $developerPosition = $this->str($reportData['developer_position'] ?? '');
        $dateFrom = $this->str($reportData['date_from'] ?? '');
        $dateTo = $this->str($reportData['date_to'] ?? '');

        $titleParagraph = $section->addTextRun([
            'alignment'  => Jc::CENTER,
            'spaceAfter' => 120,
        ]);
        $titleParagraph->addText(
            'Отчёт о проделанной работе',
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_HEADING1 / 2,
                'bold' => true,
            ]
        );

        $periodParagraph = $section->addTextRun([
            'alignment'  => Jc::CENTER,
            'spaceAfter' => 120,
        ]);
        $periodParagraph->addText(
            'Период: ' . $dateFrom . ' — ' . $dateTo,
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_MAIN / 2,
            ]
        );

        $developerParagraph = $section->addTextRun([
            'alignment'  => Jc::CENTER,
            'spaceAfter' => 240,
        ]);
        $developerParagraph->addText(
            'Разработчик: ' . $developerName . ', ' . $developerPosition,
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_MAIN / 2,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $reportData
     */
    private function addDays(Section $section, array $reportData): void
    {
        /** @var array<int, mixed> $rawDays */
        $rawDays = is_array($reportData['days'] ?? null) ? $reportData['days'] : [];

        foreach ($rawDays as $rawDay) {
            /** @var array<string, mixed> $day */
            $day = is_array($rawDay) ? $rawDay : [];
            $this->addDaySection($section, $day);
        }
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function addDaySection(Section $section, array $day): void
    {
        $date = $this->str($day['date'] ?? '');
        /** @var array<int, mixed> $rawTasks */
        $rawTasks = is_array($day['tasks'] ?? null) ? $day['tasks'] : [];

        /** @var array<int, array<string, mixed>> $tasks */
        $tasks = array_values(array_filter(
            array_map(static fn (mixed $t): mixed => is_array($t) ? $t : null, $rawTasks),
            static fn (mixed $t): bool => $t !== null,
        ));

        $dayHeading = $section->addTextRun([
            'spaceAfter'  => 120,
            'spaceBefore' => 240,
        ]);
        $dayHeading->addText(
            $this->formatDate($date),
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_HEADING2 / 2,
                'bold' => true,
            ]
        );

        if ($tasks !== []) {
            $this->addTasksTable($section, $tasks);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    private function addTasksTable(Section $section, array $tasks): void
    {
        $tableStyle = [
            'borderSize'       => 6, // border in eighths of a point (6/8 = 0.75pt ~ 1px)
            'borderColor'      => '000000',
            'width'            => self::TABLE_WIDTH * 50, // in fiftieths of a percent
            'unit'             => TblWidth::PERCENT,
            'cellMarginTop'    => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft'   => 80,
            'cellMarginRight'  => 80,
        ];

        $headerCellStyle = [
            'bgColor' => 'EEEEEE',
        ];

        $headerFontStyle = [
            'name' => self::FONT_MAIN,
            'size' => self::SIZE_TABLE / 2,
            'bold' => true,
        ];

        $cellFontStyle = [
            'name' => self::FONT_MAIN,
            'size' => self::SIZE_TABLE / 2,
        ];

        $cellParagraphStyle = [
            'alignment' => Jc::LEFT,
        ];

        $table = $section->addTable($tableStyle);

        $headerRow = $table->addRow();
        $headerRow->addCell(500, $headerCellStyle)->addText('№', $headerFontStyle, $cellParagraphStyle);
        $headerRow->addCell(3000, $headerCellStyle)->addText('Задача', $headerFontStyle, $cellParagraphStyle);
        $headerRow->addCell(2000, $headerCellStyle)->addText('Проект', $headerFontStyle, $cellParagraphStyle);
        $headerRow->addCell(null, $headerCellStyle)->addText('Описание работ', $headerFontStyle, $cellParagraphStyle);

        foreach ($tasks as $task) {
            $number = $this->str($task['number'] ?? '');
            $title = $this->str($task['title'] ?? '');
            $projectName = $this->str($task['project_name'] ?? '');
            $taskNarrative = $this->str($task['narrative'] ?? '');

            $row = $table->addRow();
            $row->addCell(500)->addText($number, $cellFontStyle, $cellParagraphStyle);
            $row->addCell(3000)->addText($title, $cellFontStyle, $cellParagraphStyle);
            $row->addCell(2000)->addText($projectName, $cellFontStyle, $cellParagraphStyle);
            $row->addCell(null)->addText($taskNarrative, $cellFontStyle, $cellParagraphStyle);
        }
    }

    /**
     * @param  array<string, mixed>  $reportData
     */
    private function addSummary(Section $section, array $reportData): void
    {
        /** @var array<int, mixed> $rawDays */
        $rawDays = is_array($reportData['days'] ?? null) ? $reportData['days'] : [];

        $totalTasks = 0;

        foreach ($rawDays as $rawDay) {
            /** @var array<string, mixed> $day */
            $day = is_array($rawDay) ? $rawDay : [];

            /** @var array<int, mixed> $dayTasks */
            $dayTasks = is_array($day['tasks'] ?? null) ? $day['tasks'] : [];
            $totalTasks += count($dayTasks);
        }

        $summaryHeading = $section->addTextRun([
            'spaceBefore' => 240,
            'spaceAfter'  => 120,
        ]);
        $summaryHeading->addText(
            'Итого',
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_HEADING2 / 2,
                'bold' => true,
            ]
        );

        $statsParagraph = $section->addTextRun([
            'indentation' => ['firstLine' => self::INDENT_FIRST_LINE],
            'spaceAfter'  => 120,
        ]);
        $statsParagraph->addText(
            'Всего задач: ' . $totalTasks . '   |   Рабочих дней: ' . count($rawDays),
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_MAIN / 2,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $reportData
     */
    private function saveDocument(PhpWord $phpWord, array $reportData): string
    {
        $reportsDir = storage_path('app/reports');

        if (! is_dir($reportsDir) && ! mkdir($reportsDir, 0755, true)) {
            throw new RuntimeException('Failed to create reports directory: ' . $reportsDir);
        }

        $dateFrom = $this->str($reportData['date_from'] ?? 'unknown');
        $dateTo = $this->str($reportData['date_to'] ?? 'unknown');
        $filename = 'report-' . $dateFrom . '-' . $dateTo . '.docx';
        $filePath = $reportsDir . DIRECTORY_SEPARATOR . $filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Add text paragraph to a table cell.
     *
     * @param  array<string, mixed>  $fontStyle
     * @param  array<string, mixed>  $paragraphStyle
     */
    private function addTextToCell(Cell $cell, string $text, array $fontStyle, array $paragraphStyle): void
    {
        $cell->addText($text, $fontStyle, $paragraphStyle);
    }

    /**
     * Safely convert a mixed value to string.
     */
    private function str(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $dayNames = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];

        $monthNames = [
            1  => 'января',
            2  => 'февраля',
            3  => 'марта',
            4  => 'апреля',
            5  => 'мая',
            6  => 'июня',
            7  => 'июля',
            8  => 'августа',
            9  => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        $dayOfWeek = (int) date('N', $timestamp);
        $day = (int) date('j', $timestamp);
        $month = (int) date('n', $timestamp);
        $year = (int) date('Y', $timestamp);

        $dayName = $dayNames[$dayOfWeek];
        $monthName = $monthNames[$month];

        return $dayName . ', ' . $day . ' ' . $monthName . ' ' . $year . ' г.';
    }

    /**
     * Format date as DD.MM.YYYY for the weekly template header row.
     */
    private function formatDateShort(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d.m.Y', $timestamp);
    }
}

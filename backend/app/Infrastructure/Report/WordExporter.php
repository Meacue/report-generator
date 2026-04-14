<?php

declare(strict_types=1);

namespace App\Infrastructure\Report;

use App\Domain\Report\DTOs\ReportExportData;
use App\Domain\Report\DTOs\ReportExportDay;
use App\Domain\Report\DTOs\ReportExportTask;
use App\Domain\Report\DTOs\ReportExportUnclassifiedCommit;
use App\Domain\Report\Services\ReportExporterInterface;
use PhpOffice\PhpWord\ComplexType\TblWidth as ComplexTblWidth;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
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
     * @return string Path to generated file
     */
    public function export(ReportExportData $reportData): string
    {
        return match ($reportData->type) {
            'weekly'  => $this->exportWeekly($reportData),
            'monthly' => $this->exportMonthly($reportData),
            default   => $this->exportDefault($reportData),
        };
    }

    private function exportWeekly(ReportExportData $reportData): string
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

        $developerName = $reportData->developerName !== '' ? $reportData->developerName : '_______________';
        $developerPosition = $reportData->developerPosition !== '' ? $reportData->developerPosition : '_______________';
        $dateFrom = $reportData->dateFrom !== '' ? $reportData->dateFrom : '___________';
        $dateTo = $reportData->dateTo !== '' ? $reportData->dateTo : '___________';

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
        $this->addWeeklyDayRows($table, $reportData->days, $fontStyle, $fontBold, $paragraphStyle);

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
     * @param  list<ReportExportDay>  $days
     * @param  array<string, mixed>  $fontStyle
     * @param  array<string, mixed>  $fontBold
     * @param  array<string, mixed>  $paragraphStyle
     */
    private function addWeeklyDayRows(
        Table $table,
        array $days,
        array $fontStyle,
        array $fontBold,
        array $paragraphStyle,
    ): void {
        $russianDays = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница'];

        for ($i = 0; $i < 5; $i++) {
            $table->addRow();
            $dayCell = $table->addCell(self::WEEKLY_TABLE_WIDTH, ['valign' => 'top']);

            $day = $days[$i] ?? null;

            if ($day !== null) {
                $dayName = $russianDays[$i];
                $formattedDate = $this->formatDateShort($day->date);
                $this->addTextToCell($dayCell, "{$dayName}, {$formattedDate}:", $fontBold, $paragraphStyle);

                foreach ($day->tasks as $task) {
                    if ($task->title !== '') {
                        $this->addTextToCell($dayCell, "• {$task->title}", $fontBold, $paragraphStyle);
                    }

                    if ($task->narrative !== '') {
                        $this->addTextToCell($dayCell, "  {$task->narrative}", $fontStyle, $paragraphStyle);
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

    private function exportMonthly(ReportExportData $reportData): string
    {
        $phpWord = $this->createPhpWord();
        $section = $this->createSection($phpWord);

        $developerName = $reportData->developerName !== '' ? $reportData->developerName : 'Разработчик';

        $this->addMonthlyHeader($section, $developerName, $reportData->developerPosition, $reportData->dateFrom, $reportData->dateTo);

        $grouped = $this->groupTasksByProject($reportData->days);
        $this->addStatsLine($section, $grouped['totalTasks'], count($reportData->days));
        $this->addProjectSections($section, $grouped['tasksByProject']);

        $this->addUnclassifiedCommitsSection($section, $reportData->unclassifiedCommits ?? []);

        return $this->saveDocument($phpWord, $reportData);
    }

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
     * @param  list<ReportExportDay>  $days
     * @return array{tasksByProject: array<string, list<ReportExportTask>>, totalTasks: int}
     */
    private function groupTasksByProject(array $days): array
    {
        /** @var array<string, list<ReportExportTask>> $tasksByProject */
        $tasksByProject = [];
        $totalTasks = 0;

        foreach ($days as $day) {
            foreach ($day->tasks as $task) {
                $projectName = $task->projectName !== '' ? $task->projectName : 'Без проекта';

                $alreadyAdded = false;

                if (isset($tasksByProject[$projectName])) {
                    foreach ($tasksByProject[$projectName] as $existing) {
                        if ($existing->title === $task->title) {
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
     * @param  array<string, list<ReportExportTask>>  $tasksByProject
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
     * @param  list<ReportExportTask>  $tasks
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
            $isCompleted = ($task->status ?? '') === 'completed';

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
     * @param  list<ReportExportUnclassifiedCommit>  $unclassifiedCommits
     */
    private function addUnclassifiedCommitsSection(Section $section, array $unclassifiedCommits): void
    {
        if ($unclassifiedCommits === []) {
            return;
        }

        $this->addSeparatorLine($section);

        $section->addText(
            'Неклассифицированные коммиты',
            ['name'       => self::FONT_MAIN, 'size' => self::SIZE_HEADING2 / 2, 'bold' => true],
            ['spaceAfter' => 0]
        );

        $this->addSeparatorLine($section, spaceBefore: 0, spaceAfter: 120);

        foreach ($unclassifiedCommits as $commit) {
            $line = "• [repo: {$commit->repo}] {$commit->message}";

            if ($commit->branch !== '') {
                $line .= " (branch: {$commit->branch})";
            }

            $section->addText(
                $line,
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
                ['spaceAfter' => 60, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }
    }

    private function addMonthlyTaskEntry(Section $section, ReportExportTask $task): void
    {
        $idPrefix = $task->id !== null ? "Задача #{$task->id} — " : '';

        $section->addText(
            "  • {$idPrefix}{$task->title}",
            ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2, 'bold' => true],
            ['spaceAfter' => 0, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
        );

        if ($task->bitrix24Link !== null && $task->bitrix24Link !== '') {
            $section->addText(
                "    {$task->bitrix24Link}",
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
                ['spaceAfter' => 0, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }

        if ($task->narrative !== '') {
            $section->addText(
                "    {$task->narrative}",
                ['name'       => self::FONT_MAIN, 'size' => self::SIZE_MAIN / 2],
                ['spaceAfter' => 60, 'indentation' => ['left' => self::INDENT_FIRST_LINE]]
            );
        }
    }

    private function exportDefault(ReportExportData $reportData): string
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

    private function addHeader(Section $section, ReportExportData $reportData): void
    {
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
            'Период: ' . $reportData->dateFrom . ' — ' . $reportData->dateTo,
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
            'Разработчик: ' . $reportData->developerName . ', ' . $reportData->developerPosition,
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_MAIN / 2,
            ]
        );
    }

    private function addDays(Section $section, ReportExportData $reportData): void
    {
        foreach ($reportData->days as $day) {
            $this->addDaySection($section, $day);
        }
    }

    private function addDaySection(Section $section, ReportExportDay $day): void
    {
        $dayHeading = $section->addTextRun([
            'spaceAfter'  => 120,
            'spaceBefore' => 240,
        ]);
        $dayHeading->addText(
            $this->formatDate($day->date),
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_HEADING2 / 2,
                'bold' => true,
            ]
        );

        if ($day->tasks !== []) {
            $this->addTasksTable($section, $day->tasks);
        }
    }

    /**
     * @param  list<ReportExportTask>  $tasks
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
            $number = $task->number !== null ? (string) $task->number : '';

            $row = $table->addRow();
            $row->addCell(500)->addText($number, $cellFontStyle, $cellParagraphStyle);
            $row->addCell(3000)->addText($task->title, $cellFontStyle, $cellParagraphStyle);
            $row->addCell(2000)->addText($task->projectName, $cellFontStyle, $cellParagraphStyle);
            $row->addCell(null)->addText($task->narrative, $cellFontStyle, $cellParagraphStyle);
        }
    }

    private function addSummary(Section $section, ReportExportData $reportData): void
    {
        $totalTasks = 0;

        foreach ($reportData->days as $day) {
            $totalTasks += count($day->tasks);
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
            'Всего задач: ' . $totalTasks . '   |   Рабочих дней: ' . count($reportData->days),
            [
                'name' => self::FONT_MAIN,
                'size' => self::SIZE_MAIN / 2,
            ]
        );
    }

    private function saveDocument(PhpWord $phpWord, ReportExportData $reportData): string
    {
        $reportsDir = storage_path('app/reports');

        if (! is_dir($reportsDir) && ! mkdir($reportsDir, 0755, true)) {
            throw new RuntimeException('Failed to create reports directory: ' . $reportsDir);
        }

        $dateFrom = $reportData->dateFrom !== '' ? $reportData->dateFrom : 'unknown';
        $dateTo = $reportData->dateTo !== '' ? $reportData->dateTo : 'unknown';
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

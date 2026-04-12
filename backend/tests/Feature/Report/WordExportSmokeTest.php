<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Infrastructure\Report\WordExporter;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use Tests\TestCase;

class WordExportSmokeTest extends TestCase
{
    /** @var list<string> */
    private array $generatedFiles = [];

    public function test_weekly_export_creates_valid_docx_with_correct_structure(): void
    {
        $exporter = new WordExporter();

        $reportData = [
            'type'               => 'weekly',
            'developer_name'     => 'Иванов Иван Иванович',
            'developer_position' => 'Разработчик',
            'date_from'          => '2026-03-09',
            'date_to'            => '2026-03-13',
            'days'               => [
                [
                    'date'      => '2026-03-09',
                    'narrative' => 'Работал над авторизацией.',
                    'tasks'     => [
                        ['title' => 'Задача авторизации', 'narrative' => 'Реализована JWT-авторизация.'],
                    ],
                ],
                [
                    'date'      => '2026-03-10',
                    'narrative' => 'Рефакторинг модулей.',
                    'tasks'     => [],
                ],
                [
                    'date'      => '2026-03-11',
                    'narrative' => 'Покрытие тестами.',
                    'tasks'     => [],
                ],
                [
                    'date'      => '2026-03-12',
                    'narrative' => 'Ревью кода коллег.',
                    'tasks'     => [],
                ],
                [
                    'date'      => '2026-03-13',
                    'narrative' => 'Деплой на staging.',
                    'tasks'     => [],
                ],
            ],
        ];

        $filePath = $exporter->export($reportData);
        $this->generatedFiles[] = $filePath;

        $this->assertFileExists($filePath);

        $phpWord = IOFactory::load($filePath);
        $sections = $phpWord->getSections();

        $this->assertCount(1, $sections);

        $section = $sections[0];
        $style = $section->getStyle();

        $this->assertNotNull($style);
        $this->assertEquals(1440, $style->getMarginTop());
        $this->assertEquals(1440, $style->getMarginBottom());
        $this->assertEquals(1440, $style->getMarginLeft());
        $this->assertEquals(1440, $style->getMarginRight());

        $elements = $section->getElements();

        $captionFound = false;
        $tableRowCount = 0;
        $firstRowContainsFio = false;

        foreach ($elements as $element) {
            $class = get_class($element);

            if (str_contains($class, 'TextRun') || str_contains($class, 'Text')) {
                if (method_exists($element, 'getText')) {
                    $text = $element->getText();
                    if (is_string($text) && str_contains($text, 'Приложение 1. Форма еженедельного отчета')) {
                        $captionFound = true;
                    }
                }
            }
        }

        $table = $this->findFirstTable($elements);
        $tableFound = $table !== null;

        if ($table !== null) {
            $rows = $table->getRows();
            $tableRowCount = count($rows);

            if ($tableRowCount > 0) {
                $firstRow = $rows[0];
                foreach ($firstRow->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        if (method_exists($cellElement, 'getText')) {
                            $cellText = $cellElement->getText();
                            if (is_string($cellText) && str_contains($cellText, 'ФИО сотрудника')) {
                                $firstRowContainsFio = true;
                            }
                        }
                    }
                }
            }
        }

        $this->assertTrue($captionFound, 'Caption text not found in document');
        $this->assertTrue($tableFound, 'Table not found in document');
        $this->assertSame(6, $tableRowCount, 'Table should have 6 rows (header + 5 days)');
        $this->assertTrue($firstRowContainsFio, 'First row should contain "ФИО сотрудника"');
    }

    public function test_monthly_export_creates_valid_docx_with_project_sections(): void
    {
        $exporter = new WordExporter();

        $reportData = [
            'type'               => 'monthly',
            'developer_name'     => 'Петров Пётр Петрович',
            'developer_position' => 'Senior Developer',
            'date_from'          => '2026-03-01',
            'date_to'            => '2026-03-31',
            'days'               => [
                [
                    'date'      => '2026-03-03',
                    'narrative' => 'Выполнена работа по проекту.',
                    'tasks'     => [
                        [
                            'id'            => 101,
                            'title'         => 'Реализация API авторизации',
                            'project_name'  => 'CRM-System',
                            'narrative'     => 'Разработан REST API для авторизации.',
                            'status'        => 'completed',
                            'bitrix24_link' => 'https://bitrix.example.com/tasks/101',
                        ],
                        [
                            'id'            => 102,
                            'title'         => 'Оптимизация запросов к БД',
                            'project_name'  => 'CRM-System',
                            'narrative'     => 'Оптимизированы медленные SQL-запросы.',
                            'status'        => 'in_progress',
                            'bitrix24_link' => '',
                        ],
                    ],
                ],
            ],
            'unclassified_commits' => [],
        ];

        $filePath = $exporter->export($reportData);
        $this->generatedFiles[] = $filePath;

        $this->assertFileExists($filePath);

        $phpWord = IOFactory::load($filePath);
        $sections = $phpWord->getSections();
        $section = $sections[0];

        $fullText = $this->extractAllText($section);

        $this->assertStringContainsString('CRM-System', $fullText);
        $this->assertStringContainsString('Завершённые задачи', $fullText);
        $this->assertStringContainsString('В работе', $fullText);
    }

    public function test_default_export_creates_valid_docx(): void
    {
        $exporter = new WordExporter();

        $reportData = [
            'type'               => 'daily',
            'developer_name'     => 'Сидоров Сидор Сидорович',
            'developer_position' => 'Backend Developer',
            'date_from'          => '2026-03-10',
            'date_to'            => '2026-03-10',
            'days'               => [
                [
                    'date'      => '2026-03-10',
                    'narrative' => 'Выполнена разработка модуля уведомлений.',
                    'tasks'     => [
                        [
                            'number'       => 1,
                            'title'        => 'Модуль уведомлений',
                            'project_name' => 'MainProject',
                            'narrative'    => 'Реализован сервис email-уведомлений.',
                        ],
                    ],
                ],
            ],
        ];

        $filePath = $exporter->export($reportData);
        $this->generatedFiles[] = $filePath;

        $this->assertFileExists($filePath);
        $this->assertGreaterThan(0, filesize($filePath));
    }

    public function test_export_with_empty_days_includes_content(): void
    {
        $exporter = new WordExporter();

        $reportData = [
            'type'               => 'weekly',
            'developer_name'     => 'Козлов Козёл Козлович',
            'developer_position' => 'Developer',
            'date_from'          => '2026-03-09',
            'date_to'            => '2026-03-13',
            'days'               => [],
        ];

        $filePath = $exporter->export($reportData);
        $this->generatedFiles[] = $filePath;

        $this->assertFileExists($filePath);

        $phpWord = IOFactory::load($filePath);
        $sections = $phpWord->getSections();
        $section = $sections[0];

        $tableRowCount = 0;

        $table = $this->findFirstTable($section->getElements());
        if ($table !== null) {
            $tableRowCount = count($table->getRows());
        }

        $this->assertSame(6, $tableRowCount, 'Table must have 6 rows even with no days provided');
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * @param  array<AbstractElement>  $elements
     */
    private function findFirstTable(array $elements): ?Table
    {
        foreach ($elements as $element) {
            if ($element instanceof Table) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Extract all text content from a section for assertion purposes.
     */
    private function extractAllText(Section $section): string
    {
        $text = '';

        foreach ($section->getElements() as $element) {
            $text .= $this->extractTextFromElement($element);
        }

        return $text;
    }

    private function extractTextFromElement(object $element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $value = $element->getText();
            if (is_string($value)) {
                $text .= $value;
            }
        }

        if (method_exists($element, 'getElements')) {
            /** @var array<int, AbstractElement> $children */
            $children = $element->getElements();
            foreach ($children as $child) {
                $text .= $this->extractTextFromElement($child);
            }
        }

        if ($element instanceof Table) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        $text .= $this->extractTextFromElement($cellElement);
                    }
                }
            }
        }

        return $text;
    }
}

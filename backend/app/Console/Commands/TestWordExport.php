<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Report\DTOs\ReportExportData;
use App\Domain\Report\DTOs\ReportExportDay;
use App\Domain\Report\DTOs\ReportExportTask;
use App\Infrastructure\Report\WordExporter;
use Illuminate\Console\Command;

class TestWordExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:test-export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a test Word report with dummy data';

    public function handle(WordExporter $exporter): int
    {
        $this->info('Generating test Word report...');

        $reportData = $this->buildTestData();

        $filePath = $exporter->exportStandard($reportData);

        $this->info('Report generated successfully!');
        $this->line('File path: ' . $filePath);

        return self::SUCCESS;
    }

    private function buildTestData(): ReportExportData
    {
        return new ReportExportData(
            type: 'custom',
            developerName: 'Иванов Иван Иванович',
            developerPosition: 'Разработчик',
            dateFrom: '2024-01-01',
            dateTo: '2024-01-03',
            days: [
                new ReportExportDay(
                    date: '2024-01-01',
                    tasks: [
                        new ReportExportTask(
                            title: 'Реализация модуля авторизации',
                            projectName: 'ProjectX',
                            narrative: 'Реализована авторизация через OAuth2: добавлен endpoint для входа, обработана граничная ситуация с истечением токена. Код вынесен в отдельный сервис для улучшения поддерживаемости.',
                            number: 1,
                        ),
                        new ReportExportTask(
                            title: 'Интеграция с внешним API платёжного шлюза',
                            projectName: 'ProjectX',
                            narrative: 'Начата интеграция с платёжным шлюзом: изучена документация API, реализован базовый HTTP-клиент, добавлена обработка ошибок сети.',
                            number: 2,
                        ),
                    ],
                    narrative: 'В понедельник выполнена основная работа по реализации модуля авторизации и начата интеграция с внешним API.',
                ),
                new ReportExportDay(
                    date: '2024-01-02',
                    tasks: [
                        new ReportExportTask(
                            title: 'Исправление ошибки отображения таблицы цен',
                            projectName: 'MS-11 CRM',
                            narrative: 'Исправлена ошибка отображения таблицы цен на мобильных устройствах. Добавлена адаптивная вёрстка для экранов шириной менее 768px.',
                            number: 3,
                        ),
                        new ReportExportTask(
                            title: 'Рефакторинг модуля импорта данных',
                            projectName: 'MS-11 CRM',
                            narrative: 'Выполнен рефакторинг модуля импорта данных: выделен отдельный сервис для парсинга CSV-файлов, добавлена валидация входных данных.',
                            number: 4,
                        ),
                        new ReportExportTask(
                            title: 'Настройка CI/CD пайплайна',
                            projectName: 'Infrastructure',
                            narrative: 'Настроен автоматический деплой через GitLab CI: добавлены этапы тестирования, сборки и деплоя на staging-окружение.',
                            number: 5,
                        ),
                    ],
                    narrative: 'Исправлены критические ошибки в модуле импорта и выполнен рефакторинг сервисного слоя.',
                ),
                new ReportExportDay(
                    date: '2024-01-03',
                    tasks: [
                        new ReportExportTask(
                            title: 'Написание unit-тестов для сервиса авторизации',
                            projectName: 'ProjectX',
                            narrative: 'Написаны unit-тесты для сервиса авторизации: покрыты основные сценарии входа, выхода и обновления токена. Покрытие кода составило 87%.',
                            number: 6,
                        ),
                        new ReportExportTask(
                            title: 'Обновление технической документации',
                            projectName: 'ProjectX',
                            narrative: 'Обновлена техническая документация по API авторизации: добавлены примеры запросов, описаны коды ошибок и схемы данных.',
                            number: 7,
                        ),
                    ],
                    narrative: 'Завершена реализация основных функций, проведено код-ревью и написана документация.',
                ),
            ],
        );
    }
}

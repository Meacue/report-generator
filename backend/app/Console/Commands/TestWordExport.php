<?php

declare(strict_types=1);

namespace App\Console\Commands;

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

        $filePath = $exporter->export($reportData);

        $this->info('Report generated successfully!');
        $this->line('File path: ' . $filePath);

        return self::SUCCESS;
    }

    /**
     * @return array{developer_name: string, developer_position: string, date_from: string, date_to: string, days: array<int, array{date: string, narrative: string, tasks: array<int, array{number: int|string, title: string, project_name: string, narrative: string}>}>}
     */
    private function buildTestData(): array
    {
        return [
            'developer_name'     => 'Иванов Иван Иванович',
            'developer_position' => 'Разработчик',
            'date_from'          => '2024-01-01',
            'date_to'            => '2024-01-03',
            'days'               => [
                [
                    'date'      => '2024-01-01',
                    'narrative' => 'В понедельник выполнена основная работа по реализации модуля авторизации и начата интеграция с внешним API.',
                    'tasks'     => [
                        [
                            'number'       => 1,
                            'title'        => 'Реализация модуля авторизации',
                            'project_name' => 'ProjectX',
                            'narrative'    => 'Реализована авторизация через OAuth2: добавлен endpoint для входа, обработана граничная ситуация с истечением токена. Код вынесен в отдельный сервис для улучшения поддерживаемости.',
                        ],
                        [
                            'number'       => 2,
                            'title'        => 'Интеграция с внешним API платёжного шлюза',
                            'project_name' => 'ProjectX',
                            'narrative'    => 'Начата интеграция с платёжным шлюзом: изучена документация API, реализован базовый HTTP-клиент, добавлена обработка ошибок сети.',
                        ],
                    ],
                ],
                [
                    'date'      => '2024-01-02',
                    'narrative' => 'Исправлены критические ошибки в модуле импорта и выполнен рефакторинг сервисного слоя.',
                    'tasks'     => [
                        [
                            'number'       => 3,
                            'title'        => 'Исправление ошибки отображения таблицы цен',
                            'project_name' => 'MS-11 CRM',
                            'narrative'    => 'Исправлена ошибка отображения таблицы цен на мобильных устройствах. Добавлена адаптивная вёрстка для экранов шириной менее 768px.',
                        ],
                        [
                            'number'       => 4,
                            'title'        => 'Рефакторинг модуля импорта данных',
                            'project_name' => 'MS-11 CRM',
                            'narrative'    => 'Выполнен рефакторинг модуля импорта данных: выделен отдельный сервис для парсинга CSV-файлов, добавлена валидация входных данных.',
                        ],
                        [
                            'number'       => 5,
                            'title'        => 'Настройка CI/CD пайплайна',
                            'project_name' => 'Infrastructure',
                            'narrative'    => 'Настроен автоматический деплой через GitLab CI: добавлены этапы тестирования, сборки и деплоя на staging-окружение.',
                        ],
                    ],
                ],
                [
                    'date'      => '2024-01-03',
                    'narrative' => 'Завершена реализация основных функций, проведено код-ревью и написана документация.',
                    'tasks'     => [
                        [
                            'number'       => 6,
                            'title'        => 'Написание unit-тестов для сервиса авторизации',
                            'project_name' => 'ProjectX',
                            'narrative'    => 'Написаны unit-тесты для сервиса авторизации: покрыты основные сценарии входа, выхода и обновления токена. Покрытие кода составило 87%.',
                        ],
                        [
                            'number'       => 7,
                            'title'        => 'Обновление технической документации',
                            'project_name' => 'ProjectX',
                            'narrative'    => 'Обновлена техническая документация по API авторизации: добавлены примеры запросов, описаны коды ошибок и схемы данных.',
                        ],
                    ],
                ],
            ],
        ];
    }
}

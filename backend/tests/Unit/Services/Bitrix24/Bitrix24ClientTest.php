<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Bitrix24;

use App\Infrastructure\Bitrix24\Bitrix24Client;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class Bitrix24ClientTest extends TestCase
{
    private const string BASE_URL = 'https://company.bitrix24.ru/rest';

    private const string USER_ID = '1';

    private const string API_KEY = 'test_api_key';

    private Bitrix24Client $client;

    public function test_get_tasks(): void
    {
        $fixtureContent = (string) file_get_contents(base_path('tests/Fixtures/bitrix24_tasks.json'));
        /** @var array<string, mixed> $fixture */
        $fixture = json_decode($fixtureContent, true);

        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.list.json' => Http::response($fixture),
        ]);

        $tasks = $this->client->getTasks('1');

        $this->assertCount(3, $tasks);

        $this->assertSame('123', $tasks[0]['id']);
        $this->assertSame('Реализация модуля авторизации', $tasks[0]['title']);
        $this->assertSame('5', $tasks[0]['status']);
        $this->assertSame('10', $tasks[0]['groupId']);
        $this->assertSame('ProjectX', $tasks[0]['group']['name']);
        $this->assertSame('2024-01-16T18:00:00+03:00', $tasks[0]['closedDate']);

        $this->assertSame('789', $tasks[2]['id']);
        $this->assertNull($tasks[2]['closedDate']);

        $this->assertSame('1', $tasks[0]['createdBy']);
        $this->assertSame('1', $tasks[0]['responsibleId']);
        $this->assertSame([], $tasks[0]['accomplices']);
        $this->assertSame([], $tasks[0]['auditors']);

        $this->assertSame(['3'], $tasks[1]['accomplices']);
        $this->assertSame(['1'], $tasks[2]['auditors']);
    }

    public function test_get_tasks_sends_member_filter(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.list.json' => Http::response([
                'result' => ['tasks' => []],
                'total'  => 0,
            ]),
        ]);

        $this->client->getTasks('777');

        Http::assertSent(function (Request $request) {
            $body = (string) $request->body();
            /** @var array{filter?: array<string, mixed>, select?: list<string>} $decoded */
            $decoded = json_decode($body, true);

            $filter = $decoded['filter'] ?? [];
            $select = $decoded['select'] ?? [];

            $hasMember = isset($filter['MEMBER']) && $filter['MEMBER'] === '777';
            $noResponsible = ! array_key_exists('RESPONSIBLE_ID', $filter);
            $hasAllSelects = in_array('CREATED_BY', $select, true)
                && in_array('RESPONSIBLE_ID', $select, true)
                && in_array('ACCOMPLICES', $select, true)
                && in_array('AUDITORS', $select, true);

            return $hasMember && $noResponsible && $hasAllSelects;
        });
    }

    public function test_get_tasks_normalizes_missing_participants(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.list.json' => Http::response([
                'result' => [
                    'tasks' => [
                        [
                            'id'             => '42',
                            'title'          => 'Partial payload',
                            'status'         => '3',
                            'statusComplete' => '0',
                            'groupId'        => '10',
                            'group'          => ['id' => '10', 'name' => 'Project'],
                            'closedDate'     => null,
                            'url'            => '/tasks/42/',
                        ],
                    ],
                ],
                'total' => 1,
            ]),
        ]);

        $tasks = $this->client->getTasks('777');

        $this->assertCount(1, $tasks);
        $this->assertSame('', $tasks[0]['createdBy']);
        $this->assertSame('', $tasks[0]['responsibleId']);
        $this->assertSame([], $tasks[0]['accomplices']);
        $this->assertSame([], $tasks[0]['auditors']);
    }

    public function test_get_tasks_with_pagination(): void
    {
        $firstPage = [
            'result' => [
                'tasks' => [
                    [
                        'id'             => '1',
                        'title'          => 'Task 1',
                        'status'         => '3',
                        'statusComplete' => '5',
                        'groupId'        => '10',
                        'group'          => ['id' => '10', 'name' => 'Project'],
                        'closedDate'     => null,
                        'url'            => '/tasks/1/',
                    ],
                ],
            ],
            'total' => 2,
            'next'  => 50,
        ];

        $secondPage = [
            'result' => [
                'tasks' => [
                    [
                        'id'             => '2',
                        'title'          => 'Task 2',
                        'status'         => '5',
                        'statusComplete' => '5',
                        'groupId'        => '10',
                        'group'          => ['id' => '10', 'name' => 'Project'],
                        'closedDate'     => '2024-01-20T10:00:00+03:00',
                        'url'            => '/tasks/2/',
                    ],
                ],
            ],
            'total' => 2,
        ];

        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.list.json' => Http::sequence()
                ->push($firstPage)
                ->push($secondPage),
        ]);

        $tasks = $this->client->getTasks('1');

        $this->assertCount(2, $tasks);
        $this->assertSame('1', $tasks[0]['id']);
        $this->assertSame('2', $tasks[1]['id']);
    }

    public function test_get_tasks_filter_by_project(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.list.json' => Http::response([
                'result' => ['tasks' => []],
                'total'  => 0,
            ]),
        ]);

        $this->client->getTasks('1', groupId: 42);

        Http::assertSent(function (Request $request) {
            $body = (string) $request->body();
            /** @var array{filter?: array{GROUP_ID?: int}} $decoded */
            $decoded = json_decode($body, true);

            return isset($decoded['filter']['GROUP_ID']) && $decoded['filter']['GROUP_ID'] === 42;
        });
    }

    public function test_get_single_task(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([
                'result' => [
                    'task' => [
                        'id'             => '123',
                        'title'          => 'Single task',
                        'status'         => '5',
                        'statusComplete' => '5',
                        'groupId'        => '10',
                        'group'          => ['id' => '10', 'name' => 'ProjectX'],
                        'closedDate'     => '2024-01-16T18:00:00+03:00',
                        'url'            => '/tasks/123/',
                    ],
                ],
            ]),
        ]);

        $task = $this->client->getTask('123');

        $this->assertSame('123', $task['id']);
        $this->assertSame('Single task', $task['title']);
        $this->assertSame('5', $task['status']);
        $this->assertSame('ProjectX', $task['group']['name']);
    }

    public function test_is_connected(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/profile.json' => Http::response([
                'result' => [
                    'ID'   => '1',
                    'NAME' => 'Test User',
                ],
            ]),
        ]);

        $this->assertTrue($this->client->isConnected());
    }

    public function test_is_connected_returns_false_on_error(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/profile.json' => Http::response([], 500),
        ]);

        $this->assertFalse($this->client->isConnected());
    }

    public function test_retry_on_failure(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.list.json' => Http::response([], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bitrix24 API request failed');

        $this->client->getTasks('1');
    }

    public function test_get_projects(): void
    {
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/sonet_group.get.json' => Http::response([
                'result' => [
                    ['ID' => '10', 'NAME' => 'ProjectX'],
                    ['ID' => '20', 'NAME' => 'ProjectY'],
                ],
            ]),
        ]);

        $projects = $this->client->getProjects();

        $this->assertCount(2, $projects);
        $this->assertSame('10', $projects[0]['id']);
        $this->assertSame('ProjectX', $projects[0]['name']);
        $this->assertSame('20', $projects[1]['id']);
        $this->assertSame('ProjectY', $projects[1]['name']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Bitrix24Client(
            url: self::BASE_URL,
            userId: self::USER_ID,
            apiKey: self::API_KEY,
        );
    }
}

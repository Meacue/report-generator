<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Bitrix24;

use App\Domain\Bitrix24\DTOs\TimeEntryData;
use App\Infrastructure\Bitrix24\Bitrix24Client;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function test_get_time_entries_sends_correct_filter(): void
    {
        // GIVEN: a fake endpoint that returns an empty result
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/task.elapseditem.getlist.json' => Http::response([
                'result' => [],
                'total'  => 0,
            ]),
        ]);

        $from = CarbonImmutable::parse('2024-01-01T00:00:00Z');
        $to = CarbonImmutable::parse('2024-01-31T23:59:59Z');

        // WHEN: getTimeEntries is called
        iterator_to_array($this->client->getTimeEntries('42', $from, $to));

        // THEN: the outgoing request body contains the expected FILTER keys
        Http::assertSent(function (Request $request) use ($from, $to) {
            $body = (string) $request->body();
            /** @var array{FILTER?: array<string, string>} $decoded */
            $decoded = json_decode($body, true);

            $filter = $decoded['FILTER'] ?? [];

            return isset($filter['>=CREATED_DATE'])
                && isset($filter['<=CREATED_DATE'], $filter['USER_ID'])

                && $filter['USER_ID'] === '42'
                && $filter['>=CREATED_DATE'] === $from->toIso8601String()
                && $filter['<=CREATED_DATE'] === $to->toIso8601String();
        });
    }

    public function test_get_time_entries_paginates(): void
    {
        // GIVEN: two pages of results; page 1 has `next=50`, page 2 does not
        $page1 = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/bitrix24_time_entries_page1.json')),
            true,
        );
        $page2 = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/bitrix24_time_entries_page2.json')),
            true,
        );

        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/task.elapseditem.getlist.json' => Http::sequence()
                ->push($page1)
                ->push($page2),
        ]);

        $from = CarbonImmutable::parse('2024-01-01T00:00:00Z');
        $to = CarbonImmutable::parse('2024-01-31T23:59:59Z');

        // WHEN: all pages are iterated
        $entries = iterator_to_array($this->client->getTimeEntries('42', $from, $to));

        // THEN: 3 entries total (2 from page 1 + 1 from page 2)
        $this->assertCount(3, $entries);
        $this->assertContainsOnlyInstancesOf(TimeEntryData::class, $entries);
        $this->assertSame(1001, $entries[0]->bitrix24EntryId);
        $this->assertSame(1002, $entries[1]->bitrix24EntryId);
        $this->assertSame(1003, $entries[2]->bitrix24EntryId);
    }

    public function test_get_time_entries_normalizes_missing_comment(): void
    {
        // GIVEN: an entry with an empty COMMENT_TEXT
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/task.elapseditem.getlist.json' => Http::response([
                'result' => [
                    [
                        'ID'           => '2001',
                        'TASK_ID'      => '100',
                        'USER_ID'      => '42',
                        'SECONDS'      => '900',
                        'COMMENT_TEXT' => '',
                        'DATE_START'   => '2024-01-10T08:00:00+00:00',
                        'CREATED_DATE' => '2024-01-10T08:05:00+00:00',
                    ],
                ],
                'total' => 1,
            ]),
        ]);

        $from = CarbonImmutable::parse('2024-01-01T00:00:00Z');
        $to = CarbonImmutable::parse('2024-01-31T23:59:59Z');

        // WHEN: the entry is fetched
        $entries = iterator_to_array($this->client->getTimeEntries('42', $from, $to));

        // THEN: comment is null (empty string normalized to null)
        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->comment);
    }

    public function test_get_time_entries_prefers_date_start_for_tracked_at(): void
    {
        // GIVEN: an entry where DATE_START and CREATED_DATE differ
        $dateStart = '2024-01-10T08:00:00+00:00';
        $createdDate = '2024-01-10T09:00:00+00:00';

        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/task.elapseditem.getlist.json' => Http::sequence()
                ->push([
                    'result' => [
                        [
                            'ID'           => '3001',
                            'TASK_ID'      => '200',
                            'USER_ID'      => '42',
                            'SECONDS'      => '1800',
                            'COMMENT_TEXT' => 'Work done',
                            'DATE_START'   => $dateStart,
                            'CREATED_DATE' => $createdDate,
                        ],
                    ],
                    'total' => 1,
                ])
                ->push([
                    'result' => [
                        [
                            'ID'           => '3002',
                            'TASK_ID'      => '200',
                            'USER_ID'      => '42',
                            'SECONDS'      => '600',
                            'COMMENT_TEXT' => 'Fallback',
                            'DATE_START'   => '',
                            'CREATED_DATE' => $createdDate,
                        ],
                    ],
                    'total' => 1,
                ]),
        ]);

        $from = CarbonImmutable::parse('2024-01-01T00:00:00Z');
        $to = CarbonImmutable::parse('2024-01-31T23:59:59Z');

        // WHEN: first entry has DATE_START present; second has DATE_START empty
        $entries1 = iterator_to_array($this->client->getTimeEntries('42', $from, $to));
        $entries2 = iterator_to_array($this->client->getTimeEntries('42', $from, $to));

        // THEN: trackedAt equals DATE_START when present, falls back to CREATED_DATE otherwise
        $this->assertSame(
            CarbonImmutable::parse($dateStart)->utc()->toIso8601String(),
            $entries1[0]->trackedAt->toIso8601String(),
        );
        $this->assertSame(
            CarbonImmutable::parse($createdDate)->utc()->toIso8601String(),
            $entries2[0]->trackedAt->toIso8601String(),
        );
    }

    public function test_get_time_entries_handles_empty_response(): void
    {
        // GIVEN: Bitrix24 returns an empty result array
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/task.elapseditem.getlist.json' => Http::response([
                'result' => [],
                'total'  => 0,
            ]),
        ]);

        $from = CarbonImmutable::parse('2024-01-01T00:00:00Z');
        $to = CarbonImmutable::parse('2024-01-31T23:59:59Z');

        // WHEN: getTimeEntries is called
        $entries = iterator_to_array($this->client->getTimeEntries('42', $from, $to));

        // THEN: the resulting array is empty
        $this->assertCount(0, $entries);
    }

    public function test_try_get_task_returns_array_on_success(): void
    {
        // GIVEN: Bitrix24 responds with a valid task payload
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([
                'result' => [
                    'task' => [
                        'id'             => '42',
                        'title'          => 'Try task',
                        'status'         => '3',
                        'statusComplete' => '0',
                        'groupId'        => '10',
                        'group'          => ['id' => '10', 'name' => 'ProjectX'],
                        'closedDate'     => null,
                        'url'            => '/tasks/42/',
                    ],
                ],
            ]),
        ]);

        // WHEN: tryGetTask is called
        $result = $this->client->tryGetTask(42);

        // THEN: normalized task array is returned
        $this->assertNotNull($result);
        $this->assertSame('42', $result['id']);
        $this->assertSame('Try task', $result['title']);
    }

    public function test_try_get_task_returns_null_on_access_denied(): void
    {
        // GIVEN: Bitrix24 returns ACCESS_DENIED error in response body (HTTP 200)
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([
                'error'             => 'ACCESS_DENIED',
                'error_description' => 'Access denied! User does not have access to task.',
            ]),
        ]);

        // WHEN: tryGetTask is called
        $result = $this->client->tryGetTask(99);

        // THEN: null is returned (not an exception)
        $this->assertNull($result);
    }

    public function test_try_get_task_returns_null_on_task_not_found(): void
    {
        // GIVEN: Bitrix24 returns TASK_NOT_FOUND error in response body (HTTP 200)
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([
                'error'             => 'TASK_NOT_FOUND',
                'error_description' => 'Task does not exist.',
            ]),
        ]);

        // WHEN: tryGetTask is called
        $result = $this->client->tryGetTask(88);

        // THEN: null is returned (not an exception)
        $this->assertNull($result);
    }

    public function test_try_get_task_returns_null_on_http_403(): void
    {
        // GIVEN: a proxy returns HTTP 403 directly
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([], 403),
        ]);

        // WHEN: tryGetTask is called
        $result = $this->client->tryGetTask(77);

        // THEN: null is returned (not an exception)
        $this->assertNull($result);
    }

    public function test_try_get_task_returns_null_on_http_404(): void
    {
        // GIVEN: a proxy returns HTTP 404 directly
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([], 404),
        ]);

        // WHEN: tryGetTask is called
        $result = $this->client->tryGetTask(66);

        // THEN: null is returned (not an exception)
        $this->assertNull($result);
    }

    public function test_try_get_task_throws_on_server_error(): void
    {
        // GIVEN: Bitrix24 / proxy returns HTTP 500
        Http::fake([
            self::BASE_URL . '/' . self::USER_ID . '/' . self::API_KEY . '/tasks.task.get.json' => Http::response([], 500),
        ]);

        // WHEN / THEN: RuntimeException is thrown
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tasks.task.get');

        $this->client->tryGetTask(55);
    }

    public function test_throws_when_baseurl_empty(): void
    {
        // GIVEN: a client constructed with an empty base URL
        $client = new Bitrix24Client(url: '', userId: '1', apiKey: 'aaaaaaaa');

        Http::fake(['*' => Http::response(['result' => ['task' => []]])]);

        // WHEN / THEN: any API call throws RuntimeException mentioning misconfigured and baseUrl
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/misconfigured/i');
        $this->expectExceptionMessageMatches('/baseUrl/i');

        $client->getTask('1');
    }

    public function test_throws_when_userid_empty(): void
    {
        // GIVEN: a client constructed with an empty userId
        $client = new Bitrix24Client(url: self::BASE_URL, userId: '', apiKey: 'aaaaaaaa');

        Http::fake(['*' => Http::response(['result' => ['task' => []]])]);

        // WHEN / THEN: any API call throws RuntimeException mentioning misconfigured and userId
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/misconfigured/i');
        $this->expectExceptionMessageMatches('/userId/i');

        $client->getTask('1');
    }

    public function test_throws_when_apikey_empty(): void
    {
        // GIVEN: a client constructed with an empty apiKey
        $client = new Bitrix24Client(url: self::BASE_URL, userId: '1', apiKey: '');

        Http::fake(['*' => Http::response(['result' => ['task' => []]])]);

        // WHEN / THEN: any API call throws RuntimeException mentioning misconfigured and apiKey
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/misconfigured/i');
        $this->expectExceptionMessageMatches('/apiKey/i');

        $client->getTask('1');
    }

    public function test_logs_masked_url_on_http_failure(): void
    {
        // GIVEN: a client using a real apiKey and a 500 response
        $realApiKey = 'hcdim7lf2ogndgu3';
        $client = new Bitrix24Client(
            url: self::BASE_URL,
            userId: self::USER_ID,
            apiKey: $realApiKey,
        );

        Http::fake(['*' => Http::response('', 500)]);
        Log::spy();

        // WHEN: getTasks triggers a 500 failure (retries exhausted)
        try {
            $client->getTasks('1');
        } catch (RuntimeException) {
            // expected — we only care about what was logged
        }

        // THEN: Log::error was called with a masked URL that contains partial key + *** but NOT the full key
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($realApiKey): bool {
                if (! str_contains($message, 'Bitrix24 API request failed')) {
                    return false;
                }

                $loggedUrl = $context['url'] ?? '';

                // must contain masked prefix (first 4 chars + ***)
                $hasPrefix = str_contains((string) $loggedUrl, 'hcdi***');

                // must NOT expose the full api key
                $hasFullKey = str_contains((string) $loggedUrl, $realApiKey);

                return $hasPrefix && ! $hasFullKey;
            })
            ->once();
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

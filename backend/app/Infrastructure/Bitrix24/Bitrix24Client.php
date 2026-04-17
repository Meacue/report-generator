<?php

declare(strict_types=1);

namespace App\Infrastructure\Bitrix24;

use App\Domain\Bitrix24\DTOs\TimeEntryData;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class Bitrix24Client implements Bitrix24ClientInterface
{
    /**
     * Reverse mapping: human-readable status name to Bitrix24 status code.
     *
     * @var array<string, string>
     */
    private const array STATUS_REVERSE_MAP = [
        'waiting'              => '2',
        'in_progress'          => '3',
        'supposedly_completed' => '4',
        'completed'            => '5',
        'deferred'             => '6',
    ];

    private readonly string $baseUrl;

    public function __construct(
        string $url,
        private readonly string $userId,
        private readonly string $apiKey,
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
    ) {
        $this->baseUrl = $this->stripTrailingUserId(rtrim($url, '/'));
    }

    public function getTasks(
        string $userId,
        ?int $groupId = null,
        ?string $status = null,
    ): array {
        /** @var array<string, mixed> $filter */
        $filter = ['MEMBER' => $userId];

        if ($groupId !== null) {
            $filter['GROUP_ID'] = $groupId;
        }

        if ($status !== null && isset(self::STATUS_REVERSE_MAP[$status])) {
            $filter['STATUS'] = self::STATUS_REVERSE_MAP[$status];
        }

        $select = [
            'ID',
            'TITLE',
            'STATUS',
            'GROUP_ID',
            'CLOSED_DATE',
            'CREATED_BY',
            'RESPONSIBLE_ID',
            'ACCOMPLICES',
            'AUDITORS',
        ];

        /** @var list<array{id: string, title: string, status: string, statusComplete: string, groupId: string, group: array{id: string, name: string}, closedDate: string|null, url: string, createdBy: string, responsibleId: string, accomplices: list<string>, auditors: list<string>}> $allTasks */
        $allTasks = [];
        $start = 0;

        do {
            /** @var array{result: array{tasks: list<array<string, mixed>>}, total?: int, next?: int} $response */
            $response = $this->call('tasks.task.list', [
                'filter' => $filter,
                'select' => $select,
                'params' => ['NAV_PARAMS' => ['nPageSize' => 50, 'iNumPage' => (int) ($start / 50) + 1]],
                'start'  => $start,
            ]);

            $tasks = $response['result']['tasks'];

            foreach ($tasks as $task) {
                $allTasks[] = $this->normalizeTask($task);
            }

            $next = $response['next'] ?? null;
            $start = is_int($next) ? $next : 0;
        } while ($next !== null);

        return $allTasks;
    }

    public function getTask(string $taskId): array
    {
        /** @var array{result: array{task: array<string, mixed>}} $response */
        $response = $this->call('tasks.task.get', [
            'taskId' => $taskId,
        ]);

        return $this->normalizeTask($response['result']['task']);
    }

    /**
     * Try to fetch a single task, returning null on 403 (ACCESS_DENIED) or
     * 404 (TASK_NOT_FOUND) so callers can create a stub record instead.
     *
     * Bitrix24 does not use HTTP 4xx for these; it always responds 200 with
     * an `error` field in the JSON body. We treat HTTP 4xx statuses produced
     * by a proxy/WAF as equivalent and map them to null as well.
     *
     * Any other failure (connection error, 5xx, unexpected API error) is
     * re-thrown as a RuntimeException so the caller can log-and-skip.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     statusComplete: string,
     *     groupId: string,
     *     group: array{id: string, name: string},
     *     closedDate: string|null,
     *     url: string,
     *     createdBy: string,
     *     responsibleId: string,
     *     accomplices: list<string>,
     *     auditors: list<string>
     * }|null
     *
     * @throws RuntimeException
     */
    public function tryGetTask(int $taskId): ?array
    {
        $url = $this->buildUrl('tasks.task.get');

        try {
            $response = $this->httpClient()->post($url, ['taskId' => $taskId]);
        } catch (ConnectionException $e) {
            Log::error('Bitrix24 API connection error', [
                'method' => 'tasks.task.get',
                'error'  => $e->getMessage(),
            ]);

            throw new RuntimeException(
                sprintf('Bitrix24 API connection failed for method tasks.task.get: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        // HTTP 403/404 from a proxy or WAF → treat as "no access / not found"
        if ($response->status() === 403 || $response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            Log::error('Bitrix24 API request failed', [
                'method' => 'tasks.task.get',
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new RuntimeException(
                sprintf('Bitrix24 API request failed for method tasks.task.get with status %d', $response->status()),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        // Bitrix24 returns HTTP 200 with an `error` field for access/not-found errors
        if (isset($data['error'])) {
            $errorCode = is_string($data['error']) ? $data['error'] : '';

            if (in_array($errorCode, ['ACCESS_DENIED', 'TASK_NOT_FOUND'], true)) {
                return null;
            }

            $errorDescription = $data['error_description'] ?? null;
            $errorMessage = is_string($errorDescription)
                ? $errorDescription
                : $this->mixedToString($data['error']);

            Log::error('Bitrix24 API returned error', [
                'method'      => 'tasks.task.get',
                'error'       => $data['error'],
                'description' => $errorMessage,
            ]);

            throw new RuntimeException(
                sprintf('Bitrix24 API error for method tasks.task.get: %s', $errorMessage),
            );
        }

        /** @var array{result: array{task: array<string, mixed>}} $typedData */
        $typedData = $data;

        return $this->normalizeTask($typedData['result']['task']);
    }

    public function getProjects(): array
    {
        /** @var array{result: list<array<string, mixed>>} $response */
        $response = $this->call('sonet_group.get', []);

        /** @var list<array{id: string, name: string}> $projects */
        $projects = [];

        foreach ($response['result'] as $group) {
            $projects[] = [
                'id'   => $this->mixedToString($group['ID'] ?? $group['id'] ?? ''),
                'name' => $this->mixedToString($group['NAME'] ?? $group['name'] ?? ''),
            ];
        }

        return $projects;
    }

    public function isConnected(): bool
    {
        try {
            /** @var array<string, mixed> $response */
            $response = $this->call('profile', []);

            return isset($response['result']);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get time entries logged by a user within the given date range.
     *
     * Wraps the legacy Bitrix24 REST method task.elapseditem.getlist.
     * That method expects ORDER, FILTER, SELECT as top-level POST body keys
     * (unlike the newer tasks.task.list which wraps everything under params).
     *
     * @return iterable<int, TimeEntryData>
     */
    public function getTimeEntries(
        string $userId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): iterable {
        $filter = [
            '>=CREATED_DATE' => $from->toIso8601String(),
            '<=CREATED_DATE' => $to->toIso8601String(),
            'USER_ID'        => $userId,
        ];

        $select = [
            'ID',
            'TASK_ID',
            'USER_ID',
            'SECONDS',
            'COMMENT_TEXT',
            'DATE_START',
            'CREATED_DATE',
        ];

        $start = 0;
        $first = true;

        do {
            if (! $first) {
                usleep(250_000);
            }

            $first = false;

            $payload = [
                'ORDER'  => ['ID' => 'ASC'],
                'FILTER' => $filter,
                'SELECT' => $select,
                'start'  => $start,
            ];

            /** @var array{result: list<array<string, mixed>>, next?: int} $response */
            $response = $this->call('task.elapseditem.getlist', $payload);

            $items = $response['result'];

            foreach ($items as $raw) {
                yield $this->normalizeTimeEntry($raw);
            }

            $next = $response['next'] ?? null;
            $start = is_int($next) ? $next : 0;
        } while ($next !== null);
    }

    /**
     * Build the full API endpoint URL.
     */
    private function buildUrl(string $method): string
    {
        return sprintf('%s/%s/%s/%s.json', $this->baseUrl, $this->userId, $this->apiKey, $method);
    }

    /**
     * Strip trailing user_id segment from URL if already included.
     *
     * Example: "https://domain.bitrix24.ru/rest/250" -> "https://domain.bitrix24.ru/rest"
     */
    private function stripTrailingUserId(string $url): string
    {
        $suffix = '/' . $this->userId;
        if ($this->userId !== '' && str_ends_with($url, $suffix)) {
            return substr($url, 0, -strlen($suffix));
        }

        return $url;
    }

    /**
     * Create a configured HTTP client instance.
     */
    private function httpClient(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry($this->retries, 500, throw: false)
            ->acceptJson();
    }

    /**
     * Execute a Bitrix24 API call.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function call(string $method, array $params): array
    {
        $url = $this->buildUrl($method);

        try {
            $response = $this->httpClient()->post($url, $params);
        } catch (ConnectionException $e) {
            Log::error('Bitrix24 API connection error', [
                'method' => $method,
                'error'  => $e->getMessage(),
            ]);

            throw new RuntimeException(
                sprintf('Bitrix24 API connection failed for method %s: %s', $method, $e->getMessage()),
                0,
                $e,
            );
        }

        if ($response->failed()) {
            Log::error('Bitrix24 API request failed', [
                'method' => $method,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new RuntimeException(
                sprintf('Bitrix24 API request failed for method %s with status %d', $method, $response->status()),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if (isset($data['error'])) {
            $errorDescription = $data['error_description'] ?? null;
            $errorMessage = is_string($errorDescription)
                ? $errorDescription
                : $this->mixedToString($data['error']);

            Log::error('Bitrix24 API returned error', [
                'method'      => $method,
                'error'       => $data['error'],
                'description' => $errorMessage,
            ]);

            throw new RuntimeException(
                sprintf('Bitrix24 API error for method %s: %s', $method, $errorMessage),
            );
        }

        return $data;
    }

    /**
     * Safely convert a mixed value to string.
     */
    private function mixedToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Normalize a Bitrix24 task response into our standard format.
     *
     * Participant IDs (CREATED_BY, RESPONSIBLE_ID, ACCOMPLICES, AUDITORS)
     * are always coerced to strings so downstream consumers can compare
     * identifiers without worrying about Bitrix24's mixed int/string typing.
     *
     * @param  array<string, mixed>  $task
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     statusComplete: string,
     *     groupId: string,
     *     group: array{id: string, name: string},
     *     closedDate: string|null,
     *     url: string,
     *     createdBy: string,
     *     responsibleId: string,
     *     accomplices: list<string>,
     *     auditors: list<string>
     * }
     */
    private function normalizeTask(array $task): array
    {
        $id = $this->getField($task, 'id', 'ID');
        $groupId = $this->getField($task, 'groupId', 'GROUP_ID');
        $statusCode = $this->getField($task, 'status', 'STATUS');

        $rawGroup = $task['group'] ?? $task['SE_GROUP'] ?? [];
        /** @var array<string, mixed> $groupData */
        $groupData = is_array($rawGroup) ? $rawGroup : [];

        $closedDate = $task['closedDate'] ?? $task['CLOSED_DATE'] ?? null;

        $rawUrl = $task['url'] ?? $task['URL'] ?? null;
        $url = is_string($rawUrl)
            ? $rawUrl
            : sprintf('/company/personal/user/%s/tasks/task/view/%s/', $this->userId, $id);

        return [
            'id'             => $id,
            'title'          => $this->getField($task, 'title', 'TITLE'),
            'status'         => $statusCode,
            'statusComplete' => $this->getField($task, 'statusComplete', 'STATUS_COMPLETE', $statusCode),
            'groupId'        => $groupId,
            'group'          => [
                'id'   => $this->getField($groupData, 'id', 'ID', $groupId),
                'name' => $this->getField($groupData, 'name', 'NAME'),
            ],
            'closedDate'    => is_string($closedDate) ? $closedDate : null,
            'url'           => $url,
            'createdBy'     => $this->getField($task, 'createdBy', 'CREATED_BY'),
            'responsibleId' => $this->getField($task, 'responsibleId', 'RESPONSIBLE_ID'),
            'accomplices'   => $this->getIdList($task, 'accomplices', 'ACCOMPLICES'),
            'auditors'      => $this->getIdList($task, 'auditors', 'AUDITORS'),
        ];
    }

    /**
     * Read a string field from a task payload, tolerating camelCase/UPPER_CASE conventions.
     *
     * @param  array<string, mixed>  $task
     */
    private function getField(array $task, string $primaryKey, string $fallbackKey, string $default = ''): string
    {
        return $this->mixedToString($task[$primaryKey] ?? $task[$fallbackKey] ?? $default);
    }

    /**
     * Read a list of user IDs from a task payload, coercing every entry to a string.
     *
     * Bitrix24 may return `null`, a flat list, or an associative map for these fields
     * (e.g. ACCOMPLICES can come back as `["2","7"]` or `{"0":"2","1":"7"}`).
     *
     * @param  array<string, mixed>  $task
     * @return list<string>
     */
    private function getIdList(array $task, string $primaryKey, string $fallbackKey): array
    {
        $raw = $task[$primaryKey] ?? $task[$fallbackKey] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = [];
        foreach ($raw as $value) {
            $stringValue = $this->mixedToString($value);
            if ($stringValue !== '') {
                $ids[] = $stringValue;
            }
        }

        return $ids;
    }

    /**
     * Normalize a raw task.elapseditem.getlist entry into a typed TimeEntryData DTO.
     *
     * Bitrix24 returns numeric fields as strings; dates may be missing or empty.
     * DATE_START is preferred for trackedAt; falls back to CREATED_DATE when absent/empty.
     *
     * @param  array<string, mixed>  $raw
     */
    private function normalizeTimeEntry(array $raw): TimeEntryData
    {
        $entryId = (int) $this->mixedToString($raw['ID'] ?? '');
        $taskId = (int) $this->mixedToString($raw['TASK_ID'] ?? '');
        $userId = $this->mixedToString($raw['USER_ID'] ?? '');
        $seconds = (int) $this->mixedToString($raw['SECONDS'] ?? '');

        $commentRaw = $raw['COMMENT_TEXT'] ?? null;
        $comment = (is_string($commentRaw) && $commentRaw !== '') ? $commentRaw : null;

        $dateStartRaw = $raw['DATE_START'] ?? null;
        $createdDateRaw = $raw['CREATED_DATE'] ?? null;

        $sourceCreatedAt = (is_string($createdDateRaw) && $createdDateRaw !== '')
            ? CarbonImmutable::parse($createdDateRaw)->utc()
            : null;

        if (is_string($dateStartRaw) && $dateStartRaw !== '') {
            $trackedAt = CarbonImmutable::parse($dateStartRaw)->utc();
        } elseif ($sourceCreatedAt !== null) {
            $trackedAt = $sourceCreatedAt;
        } else {
            $trackedAt = CarbonImmutable::now()->utc();
        }

        return new TimeEntryData(
            bitrix24EntryId: $entryId,
            bitrix24TaskId: $taskId,
            bitrix24UserId: $userId,
            seconds: $seconds,
            comment: $comment,
            trackedAt: $trackedAt,
            sourceCreatedAt: $sourceCreatedAt,
        );
    }
}

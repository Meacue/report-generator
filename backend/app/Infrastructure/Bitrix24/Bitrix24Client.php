<?php

declare(strict_types=1);

namespace App\Infrastructure\Bitrix24;

use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
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
        $filter = ['RESPONSIBLE_ID' => $userId];

        if ($groupId !== null) {
            $filter['GROUP_ID'] = $groupId;
        }

        if ($status !== null && isset(self::STATUS_REVERSE_MAP[$status])) {
            $filter['STATUS'] = self::STATUS_REVERSE_MAP[$status];
        }

        $select = ['ID', 'TITLE', 'STATUS', 'GROUP_ID', 'CLOSED_DATE'];

        /** @var list<array{id: string, title: string, status: string, statusComplete: string, groupId: string, group: array{id: string, name: string}, closedDate: string|null, url: string}> $allTasks */
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
     * @param  array<string, mixed>  $task
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     statusComplete: string,
     *     groupId: string,
     *     group: array{id: string, name: string},
     *     closedDate: string|null,
     *     url: string
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
            'closedDate' => is_string($closedDate) ? $closedDate : null,
            'url'        => $url,
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
}

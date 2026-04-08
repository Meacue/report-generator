<?php

declare(strict_types=1);

namespace App\Services\GitLab;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GitLabClient implements GitLabClientInterface
{
    private const int PER_PAGE = 100;

    private const int TIMEOUT_SECONDS = 30;

    private const int RETRY_TIMES = 3;

    private const int RETRY_BASE_MS = 200;

    private readonly string $baseUrl;

    public function __construct(
        string $url,
        private readonly string $token,
    ) {
        $this->baseUrl = rtrim($url, '/') . '/api/v4';
    }

    /**
     * {@inheritDoc}
     */
    public function getBranches(int $projectId, ?string $search = null): array
    {
        $query = ['per_page' => self::PER_PAGE];

        if ($search !== null) {
            $query['search'] = $search;
        }

        $endpoint = "/projects/{$projectId}/repository/branches";

        /** @var array<int, array{name: string, commit: array{id: string, short_id: string, title: string, author_name: string, committed_date: string}, merged: bool, protected: bool}> $result */
        $result = $this->fetchAllPages($endpoint, $query);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommits(
        int $projectId,
        string $refName,
        ?string $author = null,
        ?string $since = null,
        ?string $until = null,
    ): array {
        $query = [
            'ref_name' => $refName,
            'per_page' => self::PER_PAGE,
        ];

        if ($author !== null) {
            $query['author'] = $author;
        }

        if ($since !== null) {
            $query['since'] = $since;
        }

        if ($until !== null) {
            $query['until'] = $until;
        }

        $endpoint = "/projects/{$projectId}/repository/commits";

        Log::info('GitLab API: fetching commits', [
            'project_id' => $projectId,
            'ref_name'   => $refName,
            'author'     => $author,
            'since'      => $since,
            'until'      => $until,
        ]);

        try {
            $response = $this->makeRequest()
                ->get($this->baseUrl . $endpoint, $query);

            $response->throw();

            /** @var array<int, array{id: string, short_id: string, title: string, message: string, author_name: string, author_email: string, committed_date: string, parent_ids: array<int, string>}> $data */
            $data = $response->json();

            Log::info('GitLab API: fetched commits', [
                'project_id' => $projectId,
                'count'      => count($data),
            ]);

            return $data;
        } catch (ConnectionException $e) {
            Log::warning('GitLab API: connection error fetching commits', [
                'project_id' => $projectId,
                'error'      => $e->getMessage(),
            ]);

            return [];
        } catch (RequestException $e) {
            Log::warning('GitLab API: request error fetching commits', [
                'project_id' => $projectId,
                'status'     => $e->response->status(),
                'error'      => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getMergeRequests(
        int $projectId,
        ?string $authorUsername = null,
        string $state = 'all',
        ?string $createdAfter = null,
        ?string $createdBefore = null,
    ): array {
        $query = [
            'per_page' => self::PER_PAGE,
            'state'    => $state,
        ];

        if ($authorUsername !== null) {
            $query['author_username'] = $authorUsername;
        }

        if ($createdAfter !== null) {
            $query['created_after'] = $createdAfter;
        }

        if ($createdBefore !== null) {
            $query['created_before'] = $createdBefore;
        }

        $endpoint = "/projects/{$projectId}/merge_requests";

        /** @var array<int, array{iid: int, title: string, description: string|null, source_branch: string, target_branch: string, state: string, author: array{username: string}, web_url: string, created_at: string, updated_at: string, merged_at: string|null}> $result */
        $result = $this->fetchAllPages($endpoint, $query);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMergeRequestCommits(int $projectId, int $mergeRequestIid): array
    {
        $query = ['per_page' => self::PER_PAGE];

        $endpoint = "/projects/{$projectId}/merge_requests/{$mergeRequestIid}/commits";

        /** @var array<int, array{id: string, short_id: string, title: string, message: string, author_name: string, author_email: string, committed_date: string, parent_ids: array<int, string>}> $result */
        $result = $this->fetchAllPages($endpoint, $query);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMergeRequestChanges(int $projectId, int $mergeRequestIid): array
    {
        $endpoint = "/projects/{$projectId}/merge_requests/{$mergeRequestIid}/changes";
        $emptyResult = ['changes_count' => 0, 'changes' => []];

        Log::info('GitLab API: fetching merge request changes', [
            'project_id' => $projectId,
            'mr_iid'     => $mergeRequestIid,
        ]);

        try {
            $response = $this->makeRequest()
                ->get($this->baseUrl . $endpoint);

            $response->throw();

            /** @var array{changes_count: int, changes: list<array{old_path: string, new_path: string, new_file: bool, renamed_file: bool, deleted_file: bool, diff: string}>} $data */
            $data = $response->json();

            $changesCount = $data['changes_count'];

            /** @var array<int, array{old_path: string, new_path: string, new_file: bool, renamed_file: bool, deleted_file: bool}> $changes */
            $changes = array_map(
                fn (array $change): array => [
                    'old_path'     => $change['old_path'],
                    'new_path'     => $change['new_path'],
                    'new_file'     => $change['new_file'],
                    'renamed_file' => $change['renamed_file'],
                    'deleted_file' => $change['deleted_file'],
                ],
                $data['changes'],
            );

            Log::info('GitLab API: fetched merge request changes', [
                'project_id'    => $projectId,
                'mr_iid'        => $mergeRequestIid,
                'changes_count' => $changesCount,
            ]);

            return [
                'changes_count' => $changesCount,
                'changes'       => $changes,
            ];
        } catch (ConnectionException $e) {
            Log::warning('GitLab API: connection error fetching merge request changes', [
                'project_id' => $projectId,
                'mr_iid'     => $mergeRequestIid,
                'error'      => $e->getMessage(),
            ]);

            return $emptyResult;
        } catch (RequestException $e) {
            Log::warning('GitLab API: request error fetching merge request changes', [
                'project_id' => $projectId,
                'mr_iid'     => $mergeRequestIid,
                'status'     => $e->response->status(),
                'error'      => $e->getMessage(),
            ]);

            return $emptyResult;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getProjects(): array
    {
        $query = [
            'per_page'   => self::PER_PAGE,
            'membership' => 'true',
            'simple'     => 'true',
        ];

        $endpoint = '/projects';

        /** @var array<int, array{id: int, name: string, path_with_namespace: string}> $result */
        $result = $this->fetchAllPages($endpoint, $query);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        try {
            Log::info('GitLab API: checking connection');

            $response = $this->makeRequest()
                ->get($this->baseUrl . '/user');

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('GitLab API: connection check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fetch all pages for a paginated endpoint.
     *
     * @param  array<string, string|int>  $query
     * @return list<mixed>
     */
    private function fetchAllPages(string $endpoint, array $query): array
    {
        /** @var list<mixed> $allResults */
        $allResults = [];
        $page = 1;

        $endpointName = basename($endpoint);

        Log::info("GitLab API: fetching {$endpointName}", [
            'endpoint' => $endpoint,
        ]);

        do {
            $query['page'] = $page;

            try {
                $response = $this->makeRequest()
                    ->get($this->baseUrl . $endpoint, $query);

                $response->throw();

                /** @var list<mixed> $data */
                $data = $response->json();

                /** @var list<mixed> $allResults */
                $allResults = array_merge($allResults, $data);

                $headerValue = $response->header('X-Total-Pages');
                $totalPages = $headerValue !== '' ? (int) $headerValue : 1;

                Log::info("GitLab API: fetched {$endpointName} page", [
                    'page'          => $page,
                    'total_pages'   => $totalPages,
                    'items_on_page' => count($data),
                ]);

                $page++;
            } catch (ConnectionException $e) {
                Log::warning("GitLab API: connection error fetching {$endpointName}", [
                    'page'  => $page,
                    'error' => $e->getMessage(),
                ]);

                break;
            } catch (RequestException $e) {
                Log::warning("GitLab API: request error fetching {$endpointName}", [
                    'page'   => $page,
                    'status' => $e->response->status(),
                    'error'  => $e->getMessage(),
                ]);

                break;
            }
        } while ($page <= $totalPages);

        return $allResults;
    }

    private function makeRequest(): PendingRequest
    {
        return Http::withToken($this->token)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(
                times: self::RETRY_TIMES,
                sleepMilliseconds: self::RETRY_BASE_MS,
                when: fn (\Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->status() >= 500),
                throw: false,
            );
    }
}

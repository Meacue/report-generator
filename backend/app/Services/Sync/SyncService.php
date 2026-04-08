<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\SyncSource;
use App\Enums\SyncStatus;
use App\Enums\TaskStatus;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\ProjectMapping;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\Task;
use App\Services\Bitrix24\Bitrix24ClientInterface;
use App\Services\GitLab\BranchParser;
use App\Services\GitLab\GitLabClientInterface;
use App\Services\Matching\MatchingEngineInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class SyncService implements SyncServiceInterface
{
    private const int MAX_CHANGED_FILES = 100;

    public function __construct(
        private readonly GitLabClientInterface $gitLabClient,
        private readonly Bitrix24ClientInterface $bitrix24Client,
        private readonly MatchingEngineInterface $matchingEngine,
        private readonly BranchParser $branchParser,
        private readonly ConventionalCommitParser $commitParser,
    ) {
    }

    public function syncAll(): void
    {
        $this->syncGitLab();
        $this->syncBitrix24();
        $this->matchingEngine->matchAllUnmatched();
    }

    public function syncGitLab(): SyncLog
    {
        $startedAt = CarbonImmutable::now();

        try {
            $itemsSynced = $this->performGitLabSync();

            return $this->createSyncLog(
                source: SyncSource::GitLab,
                status: SyncStatus::Success,
                itemsSynced: $itemsSynced,
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            return $this->createSyncLog(
                source: SyncSource::GitLab,
                status: SyncStatus::Failed,
                itemsSynced: 0,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function syncBitrix24(): SyncLog
    {
        $startedAt = CarbonImmutable::now();

        try {
            $itemsSynced = $this->performBitrix24Sync();

            return $this->createSyncLog(
                source: SyncSource::Bitrix24,
                status: SyncStatus::Success,
                itemsSynced: $itemsSynced,
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            return $this->createSyncLog(
                source: SyncSource::Bitrix24,
                status: SyncStatus::Failed,
                itemsSynced: 0,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function resync(string $dateFrom, string $dateTo): void
    {
        $startedAt = CarbonImmutable::now();

        try {
            $this->performGitLabSync($dateFrom, $dateTo);
            $this->createSyncLog(
                source: SyncSource::GitLab,
                status: SyncStatus::Success,
                itemsSynced: 0,
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            $this->createSyncLog(
                source: SyncSource::GitLab,
                status: SyncStatus::Failed,
                itemsSynced: 0,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }

        $startedAt = CarbonImmutable::now();

        try {
            $this->performBitrix24Sync();
            $this->createSyncLog(
                source: SyncSource::Bitrix24,
                status: SyncStatus::Success,
                itemsSynced: 0,
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            $this->createSyncLog(
                source: SyncSource::Bitrix24,
                status: SyncStatus::Failed,
                itemsSynced: 0,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }

        $this->matchingEngine->matchAllUnmatched();
    }

    private function performGitLabSync(?string $since = null, ?string $until = null): int
    {
        $setting = Setting::query()->first();
        $gitlabUsername = $setting?->gitlab_username;
        $gitlabEmail = $setting?->gitlab_email;

        /** @var list<ProjectMapping> $mappings */
        $mappings = ProjectMapping::all()->all();
        $itemsSynced = 0;

        foreach ($mappings as $mapping) {
            $repoId = $mapping->gitlab_repo_id;

            $mergeRequests = $this->gitLabClient->getMergeRequests(
                projectId: $repoId,
                authorUsername: $gitlabUsername,
                state: 'all',
                createdAfter: $since,
                createdBefore: $until,
            );

            foreach ($mergeRequests as $mrData) {
                $branchName = $mrData['source_branch'];
                $parsed = $this->branchParser->parse($branchName);

                /** @var Branch $branch */
                $branch = Branch::query()->updateOrCreate(
                    [
                        'gitlab_repo_id' => $repoId,
                        'branch_name'    => $branchName,
                    ],
                    [
                        'parsed_task_number'   => $parsed->parsedTaskNumber,
                        'parsed_date'          => $parsed->parsedDate,
                        'parsed_parent_branch' => $parsed->parentBranch,
                        'parsed_info'          => $parsed->info,
                        'gitlab_mr_iid'        => $mrData['iid'],
                        'mr_state'             => $mrData['state'],
                        'mr_target_branch'     => $mrData['target_branch'],
                        'mr_web_url'           => $mrData['web_url'],
                        'mr_title'             => $mrData['title'],
                        'mr_description'       => $mrData['description'] ?? null,
                        'mr_merged_at'         => $mrData['merged_at'],
                        'synced_at'            => CarbonImmutable::now(),
                    ],
                );

                $itemsSynced++;

                $this->syncMergeRequestDiffStats($branch, $repoId, $mrData['iid']);

                $commits = $this->gitLabClient->getMergeRequestCommits(
                    projectId: $repoId,
                    mergeRequestIid: $mrData['iid'],
                );

                foreach ($commits as $commitData) {
                    if (! $this->isCommitByConfiguredAuthor($commitData['author_email'], $gitlabEmail)) {
                        continue;
                    }

                    Commit::query()->updateOrCreate(
                        ['gitlab_commit_sha' => $commitData['id']],
                        [
                            'branch_id'         => $branch->id,
                            'message'           => $commitData['message'],
                            'conventional_type' => $this->commitParser->extractType($commitData['title']),
                            'author'            => $commitData['author_name'],
                            'committed_at'      => $commitData['committed_date'],
                            'synced_at'         => CarbonImmutable::now(),
                        ],
                    );

                    $itemsSynced++;
                }
            }
        }

        return $itemsSynced;
    }

    private function performBitrix24Sync(): int
    {
        $setting = Setting::query()->first();

        /** @var string|null $bitrix24UserId */
        $bitrix24UserId = $setting?->bitrix24_user_id;

        if ($bitrix24UserId === null || $bitrix24UserId === '') {
            return 0;
        }

        /** @var list<ProjectMapping> $mappings */
        $mappings = ProjectMapping::all()->all();
        $itemsSynced = 0;

        foreach ($mappings as $mapping) {
            /** @var int $groupId */
            $groupId = $mapping->bitrix24_project_id;

            $tasks = $this->bitrix24Client->getTasks(
                userId: $bitrix24UserId,
                groupId: $groupId,
            );

            foreach ($tasks as $taskData) {
                Task::query()->updateOrCreate(
                    ['bitrix24_task_id' => (int) $taskData['id']],
                    [
                        'title'             => $taskData['title'],
                        'status'            => $this->mapBitrix24Status($taskData['status']),
                        'project_id'        => (int) $taskData['groupId'],
                        'project_name'      => $taskData['group']['name'],
                        'bitrix24_url'      => $taskData['url'],
                        'status_changed_at' => $taskData['closedDate'],
                        'synced_at'         => CarbonImmutable::now(),
                    ],
                );

                $itemsSynced++;
            }
        }

        return $itemsSynced;
    }

    private function syncMergeRequestDiffStats(Branch $branch, int $repoId, int $mrIid): void
    {
        try {
            $changesData = $this->gitLabClient->getMergeRequestChanges(
                projectId: $repoId,
                mergeRequestIid: $mrIid,
            );

            $changedFiles = array_map(
                fn (array $change): string => $change['new_path'],
                $changesData['changes'],
            );

            $branch->update([
                'mr_additions'     => $changesData['changes_count'],
                'mr_deletions'     => null,
                'mr_changed_files' => array_slice($changedFiles, 0, self::MAX_CHANGED_FILES),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GitLab sync: failed to fetch MR changes', [
                'mr_iid' => $mrIid,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function isCommitByConfiguredAuthor(string $commitEmail, ?string $configuredEmail): bool
    {
        if ($configuredEmail === null || $configuredEmail === '') {
            return true;
        }

        return $commitEmail === $configuredEmail;
    }

    private function mapBitrix24Status(string $status): TaskStatus
    {
        return match ($status) {
            '5'     => TaskStatus::Completed,
            default => TaskStatus::InProgress,
        };
    }

    private function createSyncLog(
        SyncSource $source,
        SyncStatus $status,
        int $itemsSynced,
        CarbonImmutable $startedAt,
        ?string $errorMessage = null,
    ): SyncLog {
        /** @var SyncLog */
        return SyncLog::query()->create([
            'source'        => $source,
            'status'        => $status,
            'items_synced'  => $itemsSynced,
            'error_message' => $errorMessage,
            'started_at'    => $startedAt,
            'completed_at'  => CarbonImmutable::now(),
        ]);
    }
}

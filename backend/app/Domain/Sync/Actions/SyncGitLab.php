<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\GitLab\Services\BranchParser;
use App\Domain\GitLab\Services\ConventionalCommitParser;
use App\Domain\GitLab\Services\GitLabClientInterface;
use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final readonly class SyncGitLab
{
    private const int MAX_CHANGED_FILES = 100;

    public function __construct(
        private GitLabClientInterface $gitLabClient,
        private BranchParser $branchParser,
        private ConventionalCommitParser $commitParser,
    ) {
    }

    public function __invoke(?string $since = null, ?string $until = null): SyncLog
    {
        $startedAt = CarbonImmutable::now();

        try {
            $itemsSynced = $this->performSync($since, $until);

            return $this->createSyncLog(
                status: SyncStatus::Success,
                itemsSynced: $itemsSynced,
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            return $this->createSyncLog(
                status: SyncStatus::Failed,
                itemsSynced: 0,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }
    }

    private function performSync(?string $since = null, ?string $until = null): int
    {
        $setting = Setting::query()->first();
        $gitlabUsername = $setting?->gitlab_username;
        $gitlabEmail = $setting?->gitlab_email;

        if ($gitlabUsername === null || $gitlabUsername === '') {
            Log::warning('GitLab sync: gitlab_username is not configured, skipping sync');

            return 0;
        }

        $mergeRequests = $this->gitLabClient->getMergeRequests(
            projectId: null,
            authorUsername: $gitlabUsername,
            state: 'all',
            createdAfter: $since,
            createdBefore: $until,
        );

        if ($mergeRequests === []) {
            return 0;
        }

        $repoNameMap = $this->buildRepoNameMap($mergeRequests);

        $itemsSynced = 0;

        foreach ($mergeRequests as $mrData) {
            $repoId = $mrData['project_id'];
            $repoName = $repoNameMap[$repoId] ?? null;
            $branchName = $mrData['source_branch'];
            $parsed = $this->branchParser->parse($branchName);

            /** @var Branch $branch */
            $branch = Branch::query()->updateOrCreate(
                [
                    'gitlab_repo_id' => $repoId,
                    'branch_name'    => $branchName,
                ],
                [
                    'gitlab_repo_name'     => $repoName,
                    'parsed_task_number'   => $parsed->parsedTaskNumber?->value,
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

        return $itemsSynced;
    }

    /**
     * Build a map of GitLab project_id => human-readable repo name.
     *
     * Uses /projects once per sync, restricted to the project_ids referenced
     * by the MRs we just fetched. Falls back to an empty map when the projects
     * endpoint is unavailable — branches just get a null name in that case.
     *
     * @param  array<int, array{project_id: int, ...}>  $mergeRequests
     * @return array<int, string>
     */
    private function buildRepoNameMap(array $mergeRequests): array
    {
        /** @var array<int, true> $referencedIds */
        $referencedIds = [];
        foreach ($mergeRequests as $mr) {
            $referencedIds[$mr['project_id']] = true;
        }

        try {
            $projects = $this->gitLabClient->getProjects();
        } catch (\Throwable $e) {
            Log::warning('GitLab sync: failed to fetch projects for repo-name map', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        /** @var array<int, string> $map */
        $map = [];
        foreach ($projects as $project) {
            if (isset($referencedIds[$project['id']])) {
                $map[$project['id']] = $project['path_with_namespace'];
            }
        }

        return $map;
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

    private function createSyncLog(
        SyncStatus $status,
        int $itemsSynced,
        CarbonImmutable $startedAt,
        ?string $errorMessage = null,
    ): SyncLog {
        /** @var SyncLog */
        return SyncLog::query()->create([
            'source'        => SyncSource::GitLab,
            'status'        => $status,
            'items_synced'  => $itemsSynced,
            'error_message' => $errorMessage,
            'started_at'    => $startedAt,
            'completed_at'  => CarbonImmutable::now(),
        ]);
    }
}

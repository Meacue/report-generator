<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sync;

use App\Enums\SyncSource;
use App\Enums\SyncStatus;
use App\Enums\TaskStatus;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\ProjectMapping;
use App\Models\Setting;
use App\Models\Task;
use App\Services\Bitrix24\Bitrix24ClientInterface;
use App\Services\GitLab\BranchParser;
use App\Services\GitLab\GitLabClientInterface;
use App\Services\Matching\MatchingEngineInterface;
use App\Services\Sync\ConventionalCommitParser;
use App\Services\Sync\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private GitLabClientInterface&MockInterface $gitLabClient;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    private MatchingEngineInterface&MockInterface $matchingEngine;

    private SyncService $syncService;

    public function test_sync_git_lab_creates_branches_and_commits(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 42]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                [
                    'iid'           => 1,
                    'title'         => 'Feature branch',
                    'source_branch' => 'main_12345_01.03.2026',
                    'target_branch' => 'main',
                    'state'         => 'merged',
                    'author'        => ['username' => 'testuser'],
                    'web_url'       => 'https://gitlab.example.com/project/-/merge_requests/1',
                    'created_at'    => '2026-03-01T10:00:00Z',
                    'updated_at'    => '2026-03-01T12:00:00Z',
                    'merged_at'     => '2026-03-01T12:00:00Z',
                ],
            ]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequestCommits')
            ->once()
            ->andReturn([
                [
                    'id'             => 'sha123456',
                    'short_id'       => 'sha1234',
                    'title'          => 'feat: add feature',
                    'message'        => 'feat: add feature',
                    'author_name'    => 'testuser',
                    'author_email'   => 'test@example.com',
                    'committed_date' => '2026-03-01T12:00:00Z',
                    'parent_ids'     => [],
                ],
            ]);

        $log = $this->syncService->syncGitLab();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);
        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(2, $log->items_synced); // 1 branch + 1 commit
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('commits', 1);
    }

    public function test_sync_git_lab_parses_branch_names(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 42]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                [
                    'iid'           => 2,
                    'title'         => 'Parse branch test',
                    'source_branch' => 'main_99887_15.02.2026',
                    'target_branch' => 'main',
                    'state'         => 'merged',
                    'author'        => ['username' => 'testuser'],
                    'web_url'       => 'https://gitlab.example.com/project/-/merge_requests/2',
                    'created_at'    => '2026-02-15T10:00:00Z',
                    'updated_at'    => '2026-02-15T11:00:00Z',
                    'merged_at'     => '2026-02-15T11:00:00Z',
                ],
            ]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequestCommits')
            ->once()
            ->andReturn([]);

        $log = $this->syncService->syncGitLab();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertSame(99887, $branch->parsed_task_number);
        $this->assertSame('main', $branch->parsed_parent_branch);
    }

    public function test_sync_git_lab_extracts_conventional_type(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 10]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                [
                    'iid'           => 3,
                    'title'         => 'Conventional commit type test',
                    'source_branch' => 'develop_work',
                    'target_branch' => 'develop',
                    'state'         => 'merged',
                    'author'        => ['username' => 'testuser'],
                    'web_url'       => 'https://gitlab.example.com/project/-/merge_requests/3',
                    'created_at'    => '2026-03-01T10:00:00Z',
                    'updated_at'    => '2026-03-01T12:00:00Z',
                    'merged_at'     => '2026-03-01T12:00:00Z',
                ],
            ]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequestCommits')
            ->once()
            ->andReturn([
                [
                    'id'             => 'sha_feat_001',
                    'short_id'       => 'sha_f',
                    'title'          => 'feat(auth): add login',
                    'message'        => 'feat(auth): add login',
                    'author_name'    => 'testuser',
                    'author_email'   => 'test@example.com',
                    'committed_date' => '2026-03-01T12:00:00Z',
                    'parent_ids'     => [],
                ],
            ]);

        $log = $this->syncService->syncGitLab();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Commit $commit */
        $commit = Commit::query()->first();
        $this->assertSame('feat', $commit->conventional_type);
    }

    public function test_sync_bitrix24_creates_task_records(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                [
                    'id'             => '1001',
                    'title'          => 'Fix login page',
                    'status'         => '5',
                    'statusComplete' => '5',
                    'groupId'        => '5',
                    'group'          => ['id' => '5', 'name' => 'Project Alpha'],
                    'closedDate'     => '2026-03-10T15:00:00+03:00',
                    'url'            => 'https://bitrix24.example.com/task/1001',
                ],
                [
                    'id'             => '1002',
                    'title'          => 'Add dashboard',
                    'status'         => '3',
                    'statusComplete' => '0',
                    'groupId'        => '5',
                    'group'          => ['id' => '5', 'name' => 'Project Alpha'],
                    'closedDate'     => null,
                    'url'            => 'https://bitrix24.example.com/task/1002',
                ],
            ]);

        $log = $this->syncService->syncBitrix24();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);
        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(2, $log->items_synced);
        $this->assertDatabaseCount('tasks', 2);

        /** @var Task $completedTask */
        $completedTask = Task::query()->where('bitrix24_task_id', 1001)->first();
        $this->assertSame(TaskStatus::Completed, $completedTask->status);

        /** @var Task $inProgressTask */
        $inProgressTask = Task::query()->where('bitrix24_task_id', 1002)->first();
        $this->assertSame(TaskStatus::InProgress, $inProgressTask->status);
    }

    public function test_sync_all_runs_matching_after_sync(): void
    {
        Setting::factory()->create([
            'gitlab_username'  => 'testuser',
            'bitrix24_user_id' => '777',
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->andReturn([]);
        $this->bitrix24Client->shouldReceive('getTasks')->andReturn([]);

        $this->matchingEngine
            ->shouldReceive('matchAllUnmatched')
            ->once()
            ->andReturn(new Collection());

        $this->syncService->syncAll();

        // Matching engine was called (verified by shouldReceive->once())
        $this->assertDatabaseCount('sync_logs', 2); // gitlab + bitrix24
    }

    public function test_sync_creates_success_log(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 1]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->andReturn([]);

        $log = $this->syncService->syncGitLab();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->completed_at);
    }

    public function test_sync_creates_failed_log_on_error(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 1]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->andThrow(new \RuntimeException('Connection refused'));

        $log = $this->syncService->syncGitLab();

        $this->assertSame(SyncStatus::Failed, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertSame('Connection refused', $log->error_message);
        $this->assertSame(0, $log->items_synced);
    }

    public function test_sync_git_lab_stores_mr_metadata(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 42]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                [
                    'iid'           => 7,
                    'title'         => 'MR metadata test',
                    'source_branch' => 'main_55555_10.03.2026',
                    'target_branch' => 'main',
                    'state'         => 'merged',
                    'author'        => ['username' => 'testuser'],
                    'web_url'       => 'https://gitlab.example.com/project/-/merge_requests/7',
                    'created_at'    => '2026-03-10T08:00:00Z',
                    'updated_at'    => '2026-03-10T09:30:00Z',
                    'merged_at'     => '2026-03-10T09:30:00Z',
                ],
            ]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequestCommits')
            ->once()
            ->andReturn([]);

        $log = $this->syncService->syncGitLab();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertSame(7, $branch->gitlab_mr_iid);
        $this->assertSame('merged', $branch->mr_state);
        $this->assertSame('main', $branch->mr_target_branch);
        $this->assertSame('https://gitlab.example.com/project/-/merge_requests/7', $branch->mr_web_url);
        $this->assertNotNull($branch->mr_merged_at);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->gitLabClient = Mockery::mock(GitLabClientInterface::class);
        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->matchingEngine = Mockery::mock(MatchingEngineInterface::class);

        $this->syncService = new SyncService(
            gitLabClient: $this->gitLabClient,
            bitrix24Client: $this->bitrix24Client,
            matchingEngine: $this->matchingEngine,
            branchParser: new BranchParser(),
            commitParser: new ConventionalCommitParser(),
        );
    }
}

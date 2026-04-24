<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\GitLab\Services\BranchParser;
use App\Domain\GitLab\Services\ConventionalCommitParser;
use App\Domain\GitLab\Services\GitLabClientInterface;
use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Actions\SyncGitLab;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SyncGitLabTest extends TestCase
{
    use RefreshDatabase;

    private GitLabClientInterface&MockInterface $gitLabClient;

    private SyncGitLab $action;

    // ---------------------------------------------------------------------------
    // Happy path — single repo
    // ---------------------------------------------------------------------------

    public function test_creates_branches_and_commits(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                $this->makeMrPayload([
                    'iid'           => 1,
                    'project_id'    => 42,
                    'title'         => 'Feature branch',
                    'source_branch' => 'main_12345_01.03.2026',
                    'merged_at'     => '2026-03-01T12:00:00Z',
                ]),
            ]);

        $this->gitLabClient
            ->shouldReceive('getProjects')
            ->once()
            ->andReturn([
                ['id' => 42, 'name' => 'myproject', 'path_with_namespace' => 'group/myproject'],
            ]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequestChanges')
            ->once()
            ->with(42, 1)
            ->andReturn($this->makeChangesPayload(3));

        $this->gitLabClient
            ->shouldReceive('getMergeRequestCommits')
            ->once()
            ->with(42, 1)
            ->andReturn([
                $this->makeCommitPayload('sha123456', 'feat: add feature'),
            ]);

        $log = ($this->action)();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);
        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertSame(2, $log->items_synced);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('commits', 1);
    }

    // ---------------------------------------------------------------------------
    // Branch name parsing
    // ---------------------------------------------------------------------------

    public function test_parses_branch_names(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                $this->makeMrPayload([
                    'iid'           => 2,
                    'project_id'    => 42,
                    'source_branch' => 'main_99887_15.02.2026',
                    'merged_at'     => '2026-02-15T11:00:00Z',
                ]),
            ]);

        $this->gitLabClient->shouldReceive('getProjects')->once()->andReturn([
            ['id' => 42, 'name' => 'myproject', 'path_with_namespace' => 'group/myproject'],
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->once()->andReturn($this->makeChangesPayload(0));
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->once()->andReturn([]);

        $log = ($this->action)();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertSame(99887, $branch->parsed_task_number);
        $this->assertSame('main', $branch->parsed_parent_branch);
    }

    // ---------------------------------------------------------------------------
    // Conventional commit type extraction
    // ---------------------------------------------------------------------------

    public function test_extracts_conventional_type(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                $this->makeMrPayload([
                    'iid'           => 3,
                    'project_id'    => 10,
                    'source_branch' => 'develop_work',
                    'merged_at'     => '2026-03-01T12:00:00Z',
                ]),
            ]);

        $this->gitLabClient->shouldReceive('getProjects')->once()->andReturn([
            ['id' => 10, 'name' => 'backend', 'path_with_namespace' => 'group/backend'],
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->once()->andReturn($this->makeChangesPayload(0));

        $this->gitLabClient
            ->shouldReceive('getMergeRequestCommits')
            ->once()
            ->andReturn([
                $this->makeCommitPayload('sha_feat_001', 'feat(auth): add login'),
            ]);

        $log = ($this->action)();
        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Commit $commit */
        $commit = Commit::query()->first();
        $this->assertSame('feat', $commit->conventional_type);
    }

    // ---------------------------------------------------------------------------
    // MR metadata stored on branch
    // ---------------------------------------------------------------------------

    public function test_stores_mr_metadata(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->once()
            ->andReturn([
                $this->makeMrPayload([
                    'iid'           => 7,
                    'project_id'    => 42,
                    'source_branch' => 'main_55555_10.03.2026',
                    'state'         => 'merged',
                    'target_branch' => 'main',
                    'web_url'       => 'https://gitlab.example.com/project/-/merge_requests/7',
                    'merged_at'     => '2026-03-10T09:30:00Z',
                ]),
            ]);

        $this->gitLabClient->shouldReceive('getProjects')->once()->andReturn([
            ['id' => 42, 'name' => 'myproject', 'path_with_namespace' => 'group/myproject'],
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->once()->andReturn($this->makeChangesPayload(0));
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->once()->andReturn([]);

        $log = ($this->action)();
        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertSame(7, $branch->gitlab_mr_iid);
        $this->assertSame('merged', $branch->mr_state);
        $this->assertSame('main', $branch->mr_target_branch);
        $this->assertSame('https://gitlab.example.com/project/-/merge_requests/7', $branch->mr_web_url);
        $this->assertNotNull($branch->mr_merged_at);
    }

    // ---------------------------------------------------------------------------
    // Repo name resolved from getProjects map
    // ---------------------------------------------------------------------------

    public function test_stores_repo_name_from_projects_map(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->once()->andReturn([
            $this->makeMrPayload(['iid' => 10, 'project_id' => 99, 'source_branch' => 'feature_work']),
        ]);

        $this->gitLabClient->shouldReceive('getProjects')->once()->andReturn([
            ['id' => 99, 'name' => 'awesome-app', 'path_with_namespace' => 'myorg/awesome-app'],
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->once()->andReturn($this->makeChangesPayload(0));
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->once()->andReturn([]);

        ($this->action)();

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertSame('myorg/awesome-app', $branch->gitlab_repo_name);
    }

    // ---------------------------------------------------------------------------
    // Multiple repos in a single sync
    // ---------------------------------------------------------------------------

    public function test_syncs_multiple_repos_from_single_mr_call(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->once()->andReturn([
            $this->makeMrPayload(['iid' => 1, 'project_id' => 10, 'source_branch' => 'main_111_01.01.2026']),
            $this->makeMrPayload(['iid' => 2, 'project_id' => 20, 'source_branch' => 'main_222_02.01.2026']),
        ]);

        $this->gitLabClient->shouldReceive('getProjects')->once()->andReturn([
            ['id' => 10, 'name' => 'repo-a', 'path_with_namespace' => 'org/repo-a'],
            ['id' => 20, 'name' => 'repo-b', 'path_with_namespace' => 'org/repo-b'],
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->twice()->andReturn($this->makeChangesPayload(0));
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->twice()->andReturn([]);

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertDatabaseCount('branches', 2);
        $this->assertSame(2, $log->items_synced);
    }

    // ---------------------------------------------------------------------------
    // Empty gitlab_username → early return, nothing written
    // ---------------------------------------------------------------------------

    public function test_returns_success_log_with_zero_items_when_gitlab_username_empty(): void
    {
        Setting::factory()->create(['gitlab_username' => '']);

        $this->gitLabClient->shouldNotReceive('getMergeRequests');

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(0, $log->items_synced);
        $this->assertDatabaseCount('branches', 0);
    }

    public function test_returns_success_log_with_zero_items_when_no_setting_row(): void
    {
        // No Setting record — gitlab_username is null
        $this->gitLabClient->shouldNotReceive('getMergeRequests');

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(0, $log->items_synced);
    }

    // ---------------------------------------------------------------------------
    // Empty getMergeRequests result → 0 items, success
    // ---------------------------------------------------------------------------

    public function test_returns_zero_items_when_get_merge_requests_returns_empty(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);

        $this->gitLabClient->shouldReceive('getMergeRequests')->once()->andReturn([]);

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(0, $log->items_synced);
        $this->assertDatabaseCount('branches', 0);
    }

    // ---------------------------------------------------------------------------
    // Success log written
    // ---------------------------------------------------------------------------

    public function test_creates_success_log(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);

        $this->gitLabClient->shouldReceive('getMergeRequests')->andReturn([]);

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->completed_at);
    }

    // ---------------------------------------------------------------------------
    // Exception during sync → failed log
    // ---------------------------------------------------------------------------

    public function test_creates_failed_log_on_error(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->andThrow(new \RuntimeException('Connection refused'));

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Failed, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertSame('Connection refused', $log->error_message);
        $this->assertSame(0, $log->items_synced);
    }

    // ---------------------------------------------------------------------------
    // getProjects failure → repo name falls back to null, sync continues
    // ---------------------------------------------------------------------------

    public function test_continues_sync_when_get_projects_throws(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->once()->andReturn([
            $this->makeMrPayload(['iid' => 5, 'project_id' => 77, 'source_branch' => 'feature_x']),
        ]);

        $this->gitLabClient
            ->shouldReceive('getProjects')
            ->once()
            ->andThrow(new \RuntimeException('projects endpoint unavailable'));

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->once()->andReturn($this->makeChangesPayload(0));
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->once()->andReturn([]);

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertDatabaseCount('branches', 1);

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertNull($branch->gitlab_repo_name);
    }

    // ---------------------------------------------------------------------------
    // Commit filtered by email when gitlab_email is configured
    // ---------------------------------------------------------------------------

    public function test_skips_commits_not_matching_configured_email(): void
    {
        Setting::factory()->create([
            'gitlab_username' => 'testuser',
            'gitlab_email'    => 'configured@example.com',
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->once()->andReturn([
            $this->makeMrPayload(['iid' => 8, 'project_id' => 42, 'source_branch' => 'feature_email_filter']),
        ]);

        $this->gitLabClient->shouldReceive('getProjects')->once()->andReturn([
            ['id' => 42, 'name' => 'myproject', 'path_with_namespace' => 'group/myproject'],
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->once()->andReturn($this->makeChangesPayload(0));

        // Two commits: one matches, one does not
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->once()->andReturn([
            $this->makeCommitPayload('sha_match', 'fix: correct bug', 'configured@example.com'),
            $this->makeCommitPayload('sha_other', 'chore: lint', 'other@example.com'),
        ]);

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertDatabaseCount('commits', 1);
        // Branch (1) + matched commit (1) = 2
        $this->assertSame(2, $log->items_synced);
    }

    // ---------------------------------------------------------------------------
    // Idempotency: repeat sync updates existing branch, no duplicate
    // ---------------------------------------------------------------------------

    public function test_upserts_branch_on_repeat_sync(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser', 'gitlab_email' => null]);

        $mrPayload = $this->makeMrPayload([
            'iid'           => 9,
            'project_id'    => 42,
            'source_branch' => 'main_77777_20.03.2026',
            'state'         => 'opened',
        ]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->twice()->andReturn([$mrPayload]);
        $this->gitLabClient->shouldReceive('getProjects')->twice()->andReturn([
            ['id' => 42, 'name' => 'myproject', 'path_with_namespace' => 'group/myproject'],
        ]);
        $this->gitLabClient->shouldReceive('getMergeRequestChanges')->twice()->andReturn($this->makeChangesPayload(0));
        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->twice()->andReturn([]);

        ($this->action)();
        ($this->action)();

        $this->assertDatabaseCount('branches', 1);
    }

    // ---------------------------------------------------------------------------
    // setUp
    // ---------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->gitLabClient = Mockery::mock(GitLabClientInterface::class);
        $this->action = new SyncGitLab(
            gitLabClient: $this->gitLabClient,
            branchParser: new BranchParser(),
            commitParser: new ConventionalCommitParser(),
        );
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Build a minimal MR payload that satisfies SyncGitLab's field access.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     iid: int,
     *     project_id: int,
     *     title: string,
     *     source_branch: string,
     *     target_branch: string,
     *     state: string,
     *     author: array{username: string},
     *     web_url: string,
     *     created_at: string,
     *     updated_at: string,
     *     description: string|null,
     *     merged_at: string|null
     * }
     */
    private function makeMrPayload(array $overrides): array
    {
        /** @var array{
         *     iid: int,
         *     project_id: int,
         *     title: string,
         *     source_branch: string,
         *     target_branch: string,
         *     state: string,
         *     author: array{username: string},
         *     web_url: string,
         *     created_at: string,
         *     updated_at: string,
         *     description: string|null,
         *     merged_at: string|null
         * } $payload
         */
        $payload = array_merge([
            'iid'           => 1,
            'project_id'    => 42,
            'title'         => 'Some MR',
            'source_branch' => 'feature_branch',
            'target_branch' => 'main',
            'state'         => 'merged',
            'author'        => ['username' => 'testuser'],
            'web_url'       => 'https://gitlab.example.com/project/-/merge_requests/1',
            'created_at'    => '2026-03-01T10:00:00Z',
            'updated_at'    => '2026-03-01T12:00:00Z',
            'description'   => null,
            'merged_at'     => null,
        ], $overrides);

        return $payload;
    }

    /**
     * Build a minimal commit payload.
     *
     * @return array{
     *     id: string,
     *     short_id: string,
     *     title: string,
     *     message: string,
     *     author_name: string,
     *     author_email: string,
     *     committed_date: string,
     *     parent_ids: list<string>
     * }
     */
    private function makeCommitPayload(string $sha, string $title, string $email = 'test@example.com'): array
    {
        return [
            'id'             => $sha,
            'short_id'       => substr($sha, 0, 8),
            'title'          => $title,
            'message'        => $title,
            'author_name'    => 'testuser',
            'author_email'   => $email,
            'committed_date' => '2026-03-01T12:00:00Z',
            'parent_ids'     => [],
        ];
    }

    /**
     * Build a minimal getMergeRequestChanges payload.
     *
     * @return array{changes_count: int, changes: list<array{old_path: string, new_path: string, new_file: bool, renamed_file: bool, deleted_file: bool}>}
     */
    private function makeChangesPayload(int $count): array
    {
        $changes = [];
        for ($i = 0; $i < $count; $i++) {
            $changes[] = [
                'old_path'     => "src/File{$i}.php",
                'new_path'     => "src/File{$i}.php",
                'new_file'     => false,
                'renamed_file' => false,
                'deleted_file' => false,
            ];
        }

        return [
            'changes_count' => $count,
            'changes'       => $changes,
        ];
    }
}

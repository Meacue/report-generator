<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\GitLab\Services\BranchParser;
use App\Domain\GitLab\Services\ConventionalCommitParser;
use App\Domain\GitLab\Services\GitLabClientInterface;
use App\Domain\Settings\Models\ProjectMapping;
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

    public function test_creates_branches_and_commits(): void
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

        $log = ($this->action)();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);
        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertSame(2, $log->items_synced);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('commits', 1);
    }

    public function test_parses_branch_names(): void
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

        $this->gitLabClient->shouldReceive('getMergeRequestCommits')->once()->andReturn([]);

        $log = ($this->action)();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Branch $branch */
        $branch = Branch::query()->first();
        $this->assertSame(99887, $branch->parsed_task_number);
        $this->assertSame('main', $branch->parsed_parent_branch);
    }

    public function test_extracts_conventional_type(): void
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

        $log = ($this->action)();
        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);

        /** @var Commit $commit */
        $commit = Commit::query()->first();
        $this->assertSame('feat', $commit->conventional_type);
    }

    public function test_stores_mr_metadata(): void
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

    public function test_creates_success_log(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 1]);

        $this->gitLabClient->shouldReceive('getMergeRequests')->andReturn([]);

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->completed_at);
    }

    public function test_creates_failed_log_on_error(): void
    {
        Setting::factory()->create(['gitlab_username' => 'testuser']);
        ProjectMapping::factory()->create(['gitlab_repo_id' => 1]);

        $this->gitLabClient
            ->shouldReceive('getMergeRequests')
            ->andThrow(new \RuntimeException('Connection refused'));

        $log = ($this->action)();

        $this->assertSame(SyncStatus::Failed, $log->status);
        $this->assertSame(SyncSource::GitLab, $log->source);
        $this->assertSame('Connection refused', $log->error_message);
        $this->assertSame(0, $log->items_synced);
    }

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
}

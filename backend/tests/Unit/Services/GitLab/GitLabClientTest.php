<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GitLab;

use App\Services\GitLab\GitLabClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GitLabClientTest extends TestCase
{
    private GitLabClient $client;

    public function test_get_branches(): void
    {
        /** @var string $fixtureContent */
        $fixtureContent = file_get_contents(base_path('tests/Fixtures/gitlab_branches.json'));

        /** @var array<int, array{name: string, commit: array{id: string, short_id: string, title: string, author_name: string, committed_date: string}, merged: bool, protected: bool}> $fixture */
        $fixture = json_decode($fixtureContent, true, 512, JSON_THROW_ON_ERROR);

        Http::fake([
            'gitlab.example.com/api/v4/projects/1/repository/branches*' => Http::response($fixture, 200, [
                'X-Total-Pages' => '1',
            ]),
        ]);

        $branches = $this->client->getBranches(1);

        $this->assertCount(5, $branches);
        $this->assertSame('feature/PRJ-123_auth-module', $branches[0]['name']);
        $this->assertSame('abc123def456', $branches[0]['commit']['id']);
        $this->assertFalse($branches[0]['merged']);
        $this->assertFalse($branches[0]['protected']);
        $this->assertTrue($branches[4]['protected']);
    }

    public function test_get_commits_with_filters(): void
    {
        /** @var string $fixtureContent */
        $fixtureContent = file_get_contents(base_path('tests/Fixtures/gitlab_commits.json'));

        /** @var array<int, array{id: string, short_id: string, title: string, message: string, author_name: string, author_email: string, committed_date: string, parent_ids: array<int, string>}> $fixture */
        $fixture = json_decode($fixtureContent, true, 512, JSON_THROW_ON_ERROR);

        Http::fake([
            'gitlab.example.com/api/v4/projects/1/repository/commits*' => Http::response($fixture),
        ]);

        $commits = $this->client->getCommits(
            projectId: 1,
            refName: 'feature/PRJ-123_auth-module',
            author: 'Иванов И.И.',
            since: '2024-01-15T00:00:00Z',
            until: '2024-01-16T00:00:00Z',
        );

        $this->assertCount(3, $commits);
        $this->assertSame('abc123def456', $commits[0]['id']);
        $this->assertSame('Иванов И.И.', $commits[0]['author_name']);
        $this->assertSame('ivanov@example.com', $commits[0]['author_email']);

        Http::assertSent(function (Request $request): bool {
            $url = $request->url();

            return str_contains($url, 'ref_name=feature')
                && str_contains($url, 'author=')
                && str_contains($url, 'since=')
                && str_contains($url, 'until=');
        });
    }

    public function test_get_projects_list(): void
    {
        $page1 = [
            ['id' => 1, 'name' => 'Project A', 'path_with_namespace' => 'group/project-a'],
            ['id' => 2, 'name' => 'Project B', 'path_with_namespace' => 'group/project-b'],
        ];

        $page2 = [
            ['id' => 3, 'name' => 'Project C', 'path_with_namespace' => 'group/project-c'],
        ];

        $requestCount = 0;

        Http::fake(function (Request $request) use ($page1, $page2, &$requestCount): PromiseInterface {
            if (! str_contains($request->url(), '/projects')) {
                return Http::response([], 404);
            }

            $requestCount++;

            if ($requestCount === 1) {
                return Http::response($page1, 200, [
                    'X-Total-Pages' => '2',
                ]);
            }

            return Http::response($page2, 200, [
                'X-Total-Pages' => '2',
            ]);
        });

        $projects = $this->client->getProjects();

        $this->assertCount(3, $projects);
        $this->assertSame(1, $projects[0]['id']);
        $this->assertSame('Project C', $projects[2]['name']);
        $this->assertSame('group/project-c', $projects[2]['path_with_namespace']);
    }

    public function test_is_connected(): void
    {
        Http::fake([
            'gitlab.example.com/api/v4/user' => Http::response(['id' => 1, 'username' => 'testuser']),
        ]);

        $this->assertTrue($this->client->isConnected());
    }

    public function test_is_connected_returns_false_on_error(): void
    {
        Http::fake([
            'gitlab.example.com/api/v4/user' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->assertFalse($this->client->isConnected());
    }

    public function test_retry_on_failure(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount): PromiseInterface {
            $callCount++;

            if ($callCount === 1) {
                return Http::response(['error' => 'Internal Server Error'], 500);
            }

            return Http::response(['id' => 1, 'username' => 'testuser']);
        });

        $result = $this->client->isConnected();

        $this->assertTrue($result);
        $this->assertGreaterThanOrEqual(2, $callCount);
    }

    public function test_get_merge_requests(): void
    {
        /** @var string $fixtureContent */
        $fixtureContent = file_get_contents(base_path('tests/Fixtures/gitlab_merge_requests.json'));

        /** @var array<int, array{iid: int, title: string, source_branch: string, target_branch: string, state: string, author: array{username: string}, web_url: string, created_at: string, updated_at: string, merged_at: string|null}> $fixture */
        $fixture = json_decode($fixtureContent, true, 512, JSON_THROW_ON_ERROR);

        Http::fake([
            'gitlab.example.com/api/v4/projects/1/merge_requests*' => Http::response($fixture, 200, [
                'X-Total-Pages' => '1',
            ]),
        ]);

        $mergeRequests = $this->client->getMergeRequests(projectId: 1, authorUsername: 'testuser', state: 'all');

        $this->assertCount(3, $mergeRequests);
        $this->assertSame(1, $mergeRequests[0]['iid']);
        $this->assertSame('dev_53642_23.12.2025', $mergeRequests[0]['source_branch']);
        $this->assertSame('merged', $mergeRequests[0]['state']);
        $this->assertNotNull($mergeRequests[0]['merged_at']);
        $this->assertSame('opened', $mergeRequests[1]['state']);
        $this->assertNull($mergeRequests[1]['merged_at']);

        Http::assertSent(function (Request $request): bool {
            $url = $request->url();

            return str_contains($url, 'author_username=testuser')
                && str_contains($url, 'state=all');
        });
    }

    public function test_get_merge_request_commits(): void
    {
        /** @var string $fixtureContent */
        $fixtureContent = file_get_contents(base_path('tests/Fixtures/gitlab_mr_commits.json'));

        /** @var array<int, array{id: string, short_id: string, title: string, message: string, author_name: string, author_email: string, committed_date: string, parent_ids: array<int, string>}> $fixture */
        $fixture = json_decode($fixtureContent, true, 512, JSON_THROW_ON_ERROR);

        Http::fake([
            'gitlab.example.com/api/v4/projects/1/merge_requests/1/commits*' => Http::response($fixture, 200, [
                'X-Total-Pages' => '1',
            ]),
        ]);

        $commits = $this->client->getMergeRequestCommits(projectId: 1, mergeRequestIid: 1);

        $this->assertCount(2, $commits);
        $this->assertTrue(str_starts_with($commits[0]['id'], 'abc123'));
        $this->assertSame('Иванов И.И.', $commits[0]['author_name']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gitlab.token'    => 'test-token',
            'services.gitlab.url'      => 'https://gitlab.example.com',
            'services.gitlab.username' => 'testuser',
        ]);

        $this->client = new GitLabClient(
            url: 'https://gitlab.example.com',
            token: 'test-token',
        );
    }
}

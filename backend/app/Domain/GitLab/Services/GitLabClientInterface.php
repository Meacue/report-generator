<?php

declare(strict_types=1);

namespace App\Domain\GitLab\Services;

interface GitLabClientInterface
{
    /**
     * Get branches for a project, filtered by author.
     *
     * @param  int  $projectId  GitLab project ID
     * @param  string|null  $search  Optional branch name search filter
     * @return array<int, array{
     *     name: string,
     *     commit: array{
     *         id: string,
     *         short_id: string,
     *         title: string,
     *         author_name: string,
     *         committed_date: string
     *     },
     *     merged: bool,
     *     protected: bool
     * }>
     */
    public function getBranches(int $projectId, ?string $search = null): array;

    /**
     * Get commits for a specific branch, filtered by author and date range.
     *
     * @param  int  $projectId  GitLab project ID
     * @param  string  $refName  Branch name
     * @param  string|null  $author  Filter by author name
     * @param  string|null  $since  ISO 8601 date (e.g. 2024-01-01T00:00:00Z)
     * @param  string|null  $until  ISO 8601 date
     * @return array<int, array{
     *     id: string,
     *     short_id: string,
     *     title: string,
     *     message: string,
     *     author_name: string,
     *     author_email: string,
     *     committed_date: string,
     *     parent_ids: array<int, string>
     * }>
     */
    public function getCommits(
        int $projectId,
        string $refName,
        ?string $author = null,
        ?string $since = null,
        ?string $until = null,
    ): array;

    /**
     * Get merge requests for a project.
     *
     * @param  int  $projectId  GitLab project ID
     * @param  string|null  $authorUsername  Filter by author username
     * @param  string  $state  Filter by state: opened, closed, merged, all
     * @param  string|null  $createdAfter  ISO 8601 date filter
     * @param  string|null  $createdBefore  ISO 8601 date filter
     * @return array<int, array{
     *     iid: int,
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
     * }>
     */
    public function getMergeRequests(
        int $projectId,
        ?string $authorUsername = null,
        string $state = 'all',
        ?string $createdAfter = null,
        ?string $createdBefore = null,
    ): array;

    /**
     * Get commits for a specific merge request.
     *
     * @param  int  $projectId  GitLab project ID
     * @param  int  $mergeRequestIid  MR internal ID
     * @return array<int, array{
     *     id: string,
     *     short_id: string,
     *     title: string,
     *     message: string,
     *     author_name: string,
     *     author_email: string,
     *     committed_date: string,
     *     parent_ids: array<int, string>
     * }>
     */
    public function getMergeRequestCommits(int $projectId, int $mergeRequestIid): array;

    /**
     * Get changes (diff files) for a specific merge request.
     *
     * @param  int  $projectId  GitLab project ID
     * @param  int  $mergeRequestIid  MR internal ID
     * @return array{
     *     changes_count: int,
     *     changes: array<int, array{
     *         old_path: string,
     *         new_path: string,
     *         new_file: bool,
     *         renamed_file: bool,
     *         deleted_file: bool
     *     }>
     * }
     */
    public function getMergeRequestChanges(int $projectId, int $mergeRequestIid): array;

    /**
     * Get list of projects accessible by the token.
     *
     * @return array<int, array{id: int, name: string, path_with_namespace: string}>
     */
    public function getProjects(): array;

    /**
     * Check if connection is working.
     */
    public function isConnected(): bool;
}

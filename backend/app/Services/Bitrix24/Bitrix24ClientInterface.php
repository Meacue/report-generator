<?php

declare(strict_types=1);

namespace App\Services\Bitrix24;

interface Bitrix24ClientInterface
{
    /**
     * Get tasks for a user, optionally filtered by project and status.
     *
     * @param  string  $userId  Bitrix24 user ID
     * @param  int|null  $groupId  Project/group ID filter
     * @param  string|null  $status  Status filter (e.g. "completed", "in_progress")
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     statusComplete: string,
     *     groupId: string,
     *     group: array{id: string, name: string},
     *     closedDate: string|null,
     *     url: string
     * }>
     */
    public function getTasks(
        string $userId,
        ?int $groupId = null,
        ?string $status = null,
    ): array;

    /**
     * Get a single task by ID.
     *
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
    public function getTask(string $taskId): array;

    /**
     * Get list of projects/groups.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getProjects(): array;

    /**
     * Check if connection is working.
     */
    public function isConnected(): bool;
}

<?php

declare(strict_types=1);

namespace App\Domain\Bitrix24\Services;

interface Bitrix24ClientInterface
{
    /**
     * Get tasks where the user participates in any role (MEMBER filter).
     *
     * The MEMBER filter is a Bitrix24 shortcut that matches if the user is
     * the creator, the responsible, an accomplice or an auditor of a task.
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
     *     url: string,
     *     createdBy: string,
     *     responsibleId: string,
     *     accomplices: list<string>,
     *     auditors: list<string>
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
     *     url: string,
     *     createdBy: string,
     *     responsibleId: string,
     *     accomplices: list<string>,
     *     auditors: list<string>
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

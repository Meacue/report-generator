<?php

declare(strict_types=1);

namespace App\Domain\Bitrix24\Services;

use App\Domain\Bitrix24\DTOs\TimeEntryData;
use Carbon\CarbonImmutable;
use RuntimeException;

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
     * Try to get a single task by ID, returning null when the API signals
     * that the task does not exist or the caller has no access (403/404).
     *
     * Returns:
     *   - array  — task found and returned successfully.
     *   - null   — Bitrix24 returned ACCESS_DENIED or TASK_NOT_FOUND (expected
     *              "no rights / deleted" situations); callers should create a
     *              stub record instead.
     *
     * Throws RuntimeException for any other infrastructure error (timeout,
     * 5xx, unexpected API error) so the caller can decide how to handle it.
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
     * }|null
     *
     * @throws RuntimeException
     */
    public function tryGetTask(int $taskId): ?array;

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

    /**
     * Get time entries logged by a user within the given date range.
     *
     * Wraps the Bitrix24 REST method task.elapseditem.getlist.
     * Results are yielded as a lazy generator — iterate once to consume.
     *
     * @return iterable<int, TimeEntryData>
     */
    public function getTimeEntries(
        string $userId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): iterable;
}

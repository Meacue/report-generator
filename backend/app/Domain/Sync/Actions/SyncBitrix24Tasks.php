<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\ParticipationRole;
use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync all Bitrix24 tasks where the configured user participates
 * (creator / responsible / accomplice / auditor).
 *
 * Strategy:
 *   1. Try a single user-wide call (MEMBER filter, no GROUP_ID). This is the
 *      happy path — one HTTP request, deduplicated server-side.
 *   2. If that call fails (Bitrix24 is known to reject tasks.task.list without
 *      GROUP_ID on some instances), fall back to iterating every group
 *      returned by sonet_group.get and merging the per-group results.
 *
 * Tasks are upserted by bitrix24_task_id, so repeated runs are idempotent
 * regardless of which branch produced them.
 */
class SyncBitrix24Tasks
{
    public function __construct(
        private readonly Bitrix24ClientInterface $bitrix24Client,
    ) {
    }

    /**
     * Run the task synchronisation and return the number of upserted tasks.
     */
    public function __invoke(): int
    {
        $setting = Setting::query()->first();

        $bitrix24UserId = $setting?->bitrix24_user_id !== null
            ? (string) $setting->bitrix24_user_id
            : null;

        if ($bitrix24UserId === null) {
            Log::warning('Bitrix24 tasks sync: bitrix24_user_id is not configured, skipping sync');

            return 0;
        }

        $tasks = $this->fetchTasksForUser($bitrix24UserId);
        $itemsSynced = 0;

        foreach ($tasks as $taskData) {
            Task::query()->updateOrCreate(
                ['bitrix24_task_id' => (int) $taskData['id']],
                [
                    'title'               => $taskData['title'],
                    'status'              => $this->mapBitrix24Status($taskData['status']),
                    'project_id'          => $taskData['groupId'] !== '' ? (int) $taskData['groupId'] : null,
                    'project_name'        => $taskData['group']['name'],
                    'participation_roles' => $this->resolveParticipationRoles($taskData, $bitrix24UserId),
                    'is_external'         => false,
                    'bitrix24_url'        => $taskData['url'],
                    'status_changed_at'   => $taskData['closedDate'],
                    'synced_at'           => CarbonImmutable::now(),
                ],
            );

            $itemsSynced++;
        }

        return $itemsSynced;
    }

    /**
     * Fetch all tasks the user participates in, with a fallback strategy.
     *
     * @return list<array{
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
    private function fetchTasksForUser(string $userId): array
    {
        try {
            return array_values($this->bitrix24Client->getTasks(
                userId: $userId,
                groupId: null,
            ));
        } catch (Throwable $e) {
            Log::warning('Bitrix24 tasks sync: user-wide fetch failed, falling back to per-group', [
                'error' => $e->getMessage(),
            ]);

            return $this->fetchTasksPerGroup($userId);
        }
    }

    /**
     * Fallback: enumerate every group the user belongs to and aggregate
     * their tasks, deduplicating by bitrix24 task id.
     *
     * @return list<array{
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
    private function fetchTasksPerGroup(string $userId): array
    {
        try {
            $groups = $this->bitrix24Client->getProjects();
        } catch (Throwable $e) {
            Log::error('Bitrix24 tasks sync: sonet_group.get failed, cannot enumerate groups', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        /** @var array<string, array{
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
         * }> $deduped
         */
        $deduped = [];

        foreach ($groups as $group) {
            $groupId = (int) $group['id'];

            if ($groupId <= 0) {
                continue;
            }

            try {
                $tasks = $this->bitrix24Client->getTasks(
                    userId: $userId,
                    groupId: $groupId,
                );
            } catch (Throwable $e) {
                Log::warning('Bitrix24 tasks sync: per-group fetch failed, skipping group', [
                    'group_id' => $groupId,
                    'error'    => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($tasks as $task) {
                $deduped[$task['id']] = $task;
            }
        }

        return array_values($deduped);
    }

    /**
     * Compute the sorted list of roles the current user has in the task.
     *
     * Bitrix24 returns IDs as strings (e.g. "777"); we compare as strings so
     * numeric/string mismatches never drop a role.
     *
     * @param  array{
     *     createdBy: string,
     *     responsibleId: string,
     *     accomplices: list<string>,
     *     auditors: list<string>
     * }  $task
     * @return list<string>
     */
    private function resolveParticipationRoles(array $task, string $userId): array
    {
        /** @var array<string, true> $roles */
        $roles = [];

        if ($task['createdBy'] === $userId) {
            $roles[ParticipationRole::Creator->value] = true;
        }

        if ($task['responsibleId'] === $userId) {
            $roles[ParticipationRole::Responsible->value] = true;
        }

        if (in_array($userId, $task['accomplices'], true)) {
            $roles[ParticipationRole::Accomplice->value] = true;
        }

        if (in_array($userId, $task['auditors'], true)) {
            $roles[ParticipationRole::Auditor->value] = true;
        }

        $values = array_keys($roles);
        sort($values);

        return $values;
    }

    private function mapBitrix24Status(string $status): TaskStatus
    {
        return match ($status) {
            '5'     => TaskStatus::Completed,
            default => TaskStatus::InProgress,
        };
    }
}

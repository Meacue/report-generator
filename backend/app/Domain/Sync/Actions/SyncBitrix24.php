<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\ParticipationRole;
use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\ProjectMapping;
use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncLog;
use Carbon\CarbonImmutable;

final readonly class SyncBitrix24
{
    public function __construct(
        private Bitrix24ClientInterface $bitrix24Client,
    ) {
    }

    public function __invoke(): SyncLog
    {
        $startedAt = CarbonImmutable::now();

        try {
            $itemsSynced = $this->performSync();

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

    private function performSync(): int
    {
        $setting = Setting::query()->first();

        /** @var string|null $bitrix24UserId */
        $bitrix24UserId = $setting?->bitrix24_user_id;

        if ($bitrix24UserId === null || $bitrix24UserId === '') {
            return 0;
        }

        /** @var list<ProjectMapping> $mappings */
        $mappings = ProjectMapping::all()->all();
        $itemsSynced = 0;

        foreach ($mappings as $mapping) {
            /** @var int $groupId */
            $groupId = $mapping->bitrix24_project_id;

            $tasks = $this->bitrix24Client->getTasks(
                userId: $bitrix24UserId,
                groupId: $groupId,
            );

            foreach ($tasks as $taskData) {
                Task::query()->updateOrCreate(
                    ['bitrix24_task_id' => (int) $taskData['id']],
                    [
                        'title'               => $taskData['title'],
                        'status'              => $this->mapBitrix24Status($taskData['status']),
                        'project_id'          => (int) $taskData['groupId'],
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
        }

        return $itemsSynced;
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

    private function createSyncLog(
        SyncStatus $status,
        int $itemsSynced,
        CarbonImmutable $startedAt,
        ?string $errorMessage = null,
    ): SyncLog {
        /** @var SyncLog */
        return SyncLog::query()->create([
            'source'        => SyncSource::Bitrix24,
            'status'        => $status,
            'items_synced'  => $itemsSynced,
            'error_message' => $errorMessage,
            'started_at'    => $startedAt,
            'completed_at'  => CarbonImmutable::now(),
        ]);
    }
}

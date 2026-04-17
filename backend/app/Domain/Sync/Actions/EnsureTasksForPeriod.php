<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Given a list of Bitrix24 task IDs referenced by time-tracking entries,
 * fetch any that are missing from the local tasks table and persist them
 * as external tasks (is_external=true, participation_roles=[]).
 *
 * When Bitrix24 returns ACCESS_DENIED or TASK_NOT_FOUND (mapped to null by
 * tryGetTask), a stub record is stored with title=null so downstream report
 * rendering can label the task "Задача без названия (#ID)" / "Untitled (#ID)".
 *
 * Infrastructure errors (connection, 5xx) are logged and skipped so one bad
 * task cannot break the whole sync. This action is idempotent: calling it
 * multiple times with the same IDs will not duplicate rows.
 *
 * Prepares for Phase F where time-tracking-only tasks are backfilled before
 * report generation.
 */
final class EnsureTasksForPeriod
{
    public function __construct(
        private readonly Bitrix24ClientInterface $bitrix24Client,
    ) {
    }

    /**
     * @param  list<int>  $bitrix24TaskIds
     * @return int Number of tasks created or updated (upserted).
     */
    public function __invoke(array $bitrix24TaskIds): int
    {
        if ($bitrix24TaskIds === []) {
            return 0;
        }

        // 1. Find IDs already present in the local DB
        /** @var list<int> $existingIds */
        $existingIds = Task::query()
            ->whereIn('bitrix24_task_id', $bitrix24TaskIds)
            ->pluck('bitrix24_task_id')
            ->all();

        // 2. Compute the set of missing IDs
        $missingIds = array_values(array_diff($bitrix24TaskIds, $existingIds));

        if ($missingIds === []) {
            return 0;
        }

        $upserted = 0;

        // 3. For each missing ID, try to fetch from Bitrix24 and persist
        foreach ($missingIds as $taskId) {
            try {
                $taskData = $this->bitrix24Client->tryGetTask($taskId);
            } catch (RuntimeException $e) {
                // Infrastructure error: log and continue so one bad task
                // does not abort the entire backfill.
                Log::warning('EnsureTasksForPeriod: could not fetch task from Bitrix24', [
                    'task_id' => $taskId,
                    'error'   => $e->getMessage(),
                ]);

                continue;
            }

            if ($taskData !== null) {
                // Task found — persist with real data as an external task
                Task::query()->updateOrCreate(
                    ['bitrix24_task_id' => $taskId],
                    [
                        'title'               => $taskData['title'],
                        'status'              => $this->mapStatus($taskData['status']),
                        'project_id'          => $taskData['groupId'] !== '' ? (int) $taskData['groupId'] : null,
                        'project_name'        => $taskData['group']['name'] !== '' ? $taskData['group']['name'] : null,
                        'bitrix24_url'        => $taskData['url'] !== '' ? $taskData['url'] : null,
                        'status_changed_at'   => $taskData['closedDate'],
                        'participation_roles' => [],
                        'is_external'         => true,
                        'synced_at'           => CarbonImmutable::now(),
                    ],
                );
            } else {
                // 403/404 — create a stub so downstream consumers can still
                // reference this task ID with a graceful label.
                // project_id is nullable in the schema, so null is safe here.
                Task::query()->updateOrCreate(
                    ['bitrix24_task_id' => $taskId],
                    [
                        'title'               => null,
                        'status'              => TaskStatus::InProgress,
                        'project_id'          => null,
                        'project_name'        => null,
                        'bitrix24_url'        => null,
                        'status_changed_at'   => null,
                        'participation_roles' => [],
                        'is_external'         => true,
                        'synced_at'           => CarbonImmutable::now(),
                    ],
                );
            }

            $upserted++;
        }

        return $upserted;
    }

    private function mapStatus(string $status): TaskStatus
    {
        return match ($status) {
            '5'     => TaskStatus::Completed,
            default => TaskStatus::InProgress,
        };
    }
}

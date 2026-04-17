<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use Carbon\CarbonImmutable;

/**
 * Sync Bitrix24 time-tracking entries for the configured user within the
 * given date range.
 *
 * Acts as a safety-net for the Inbox: persisting recent time entries ensures
 * tasks appear even before a report is generated. Entries are upserted by
 * bitrix24_entry_id, making repeated runs fully idempotent.
 */
class SyncBitrix24TimeEntries
{
    public function __construct(
        private readonly Bitrix24ClientInterface $bitrix24Client,
    ) {
    }

    /**
     * Run the time-entry synchronisation and return the number of upserted records.
     */
    public function __invoke(DateRange $period): int
    {
        $setting = Setting::query()->first();

        $bitrix24UserId = $setting?->bitrix24_user_id !== null
            ? (string) $setting->bitrix24_user_id
            : null;

        if ($bitrix24UserId === null) {
            return 0;
        }

        $entries = $this->bitrix24Client->getTimeEntries(
            userId: $bitrix24UserId,
            from: $period->from,
            to: $period->to,
        );

        $count = 0;

        foreach ($entries as $entry) {
            TimeEntry::query()->updateOrCreate(
                ['bitrix24_entry_id' => $entry->bitrix24EntryId],
                [
                    'bitrix24_task_id'  => $entry->bitrix24TaskId,
                    'bitrix24_user_id'  => $entry->bitrix24UserId,
                    'seconds'           => $entry->seconds,
                    'comment'           => $entry->comment,
                    'tracked_at'        => $entry->trackedAt,
                    'source_created_at' => $entry->sourceCreatedAt,
                    'synced_at'         => CarbonImmutable::now(),
                ],
            );

            $count++;
        }

        return $count;
    }
}

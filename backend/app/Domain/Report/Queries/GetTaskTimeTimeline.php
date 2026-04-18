<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Support\Facades\DB;

/**
 * Returns per-day, per-task time breakdown for the chronology block.
 *
 * @return array<string, array<int, int>>  map of "Y-m-d" => [bitrix24_task_id => total_seconds]
 */
final readonly class GetTaskTimeTimeline
{
    /**
     * @return array<string, array<int, int>>
     */
    public function __invoke(DateRange $period, ?string $userId = null): array
    {
        if ($userId === null) {
            $setting = Setting::first();

            if ($setting === null || $setting->bitrix24_user_id === null) {
                return [];
            }

            $userId = (string) $setting->bitrix24_user_id;
        }

        /** @var array<int, object{day: string, bitrix24_task_id: int, total_seconds: int}> $rows */
        $rows = DB::table('task_time_entries')
            ->select(
                DB::raw('DATE(tracked_at) as day'),
                'bitrix24_task_id',
                DB::raw('SUM(seconds) as total_seconds'),
            )
            ->where('bitrix24_user_id', $userId)
            ->whereBetween('tracked_at', [$period->from, $period->to->endOfDay()])
            ->groupBy(DB::raw('DATE(tracked_at)'), 'bitrix24_task_id')
            ->orderBy(DB::raw('DATE(tracked_at)'))
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $day = $row->day;
            $taskId = (int) $row->bitrix24_task_id;
            $seconds = (int) $row->total_seconds;

            if (! isset($result[$day])) {
                $result[$day] = [];
            }

            $result[$day][$taskId] = $seconds;
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Support\Facades\DB;

/**
 * Returns total seconds tracked per Bitrix24 task for a given period and user.
 *
 * @return array<int, int>  map of bitrix24_task_id => total_seconds
 */
final readonly class GetTaskTimeBreakdown
{
    /**
     * @return array<int, int>
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

        /** @var array<int, object{bitrix24_task_id: int, total_seconds: int}> $rows */
        $rows = DB::table('task_time_entries')
            ->select('bitrix24_task_id', DB::raw('SUM(seconds) as total_seconds'))
            ->where('bitrix24_user_id', $userId)
            ->whereBetween('tracked_at', [$period->from, $period->to->endOfDay()])
            ->groupBy('bitrix24_task_id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->bitrix24_task_id] = (int) $row->total_seconds;
        }

        return $result;
    }
}

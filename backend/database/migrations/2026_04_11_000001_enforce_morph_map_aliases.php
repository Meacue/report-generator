<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $mappings = [
            'App\\Models\\ReportTask'    => 'report_task',
            'App\\Models\\ReportDay'     => 'report_day',
            'App\\Models\\ReportDayTask' => 'report_day_task',
        ];

        foreach ($mappings as $oldType => $newAlias) {
            DB::table('narrative_history')
                ->where('narratable_type', $oldType)
                ->update(['narratable_type' => $newAlias]);
        }
    }

    public function down(): void
    {
        $mappings = [
            'report_task'     => 'App\\Models\\ReportTask',
            'report_day'      => 'App\\Models\\ReportDay',
            'report_day_task' => 'App\\Models\\ReportDayTask',
        ];

        foreach ($mappings as $alias => $oldType) {
            DB::table('narrative_history')
                ->where('narratable_type', $alias)
                ->update(['narratable_type' => $oldType]);
        }
    }
};

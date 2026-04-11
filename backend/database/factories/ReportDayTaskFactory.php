<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportDayTask>
 */
class ReportDayTaskFactory extends Factory
{
    protected $model = ReportDayTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_day_id'  => ReportDay::factory(),
            'report_task_id' => ReportTask::factory(),
            'narrative'      => null,
            'is_edited'      => false,
        ];
    }
}

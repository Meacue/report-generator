<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportTask>
 */
class ReportTaskFactory extends Factory
{
    protected $model = ReportTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id'    => Report::factory(),
            'task_id'      => Task::factory(),
            'narrative'    => fake()->paragraph(2),
            'project_name' => 'Project ' . fake()->bothify('??-##'),
            'is_edited'    => fake()->boolean(20),
        ];
    }

    /**
     * Report task with a manually edited narrative.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
        ]);
    }

    /**
     * Report task with an LLM-generated narrative (not manually edited).
     */
    public function llmGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => false,
            'narrative' => fake()->paragraph(2),
        ]);
    }
}

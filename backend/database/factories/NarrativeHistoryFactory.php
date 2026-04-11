<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NarrativeSource;
use App\Models\NarrativeHistory;
use App\Models\ReportTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NarrativeHistory>
 */
class NarrativeHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'narratable_type'    => 'report_task',
            'narratable_id'      => ReportTask::factory(),
            'previous_narrative' => fake()->paragraph(2),
            'changed_at'         => fake()->dateTimeBetween('-1 month', 'now'),
            'source'             => fake()->randomElement(NarrativeSource::cases()),
        ];
    }

    /**
     * History entry from a manual edit.
     */
    public function fromManualEdit(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => NarrativeSource::ManualEdit,
        ]);
    }

    /**
     * History entry from an LLM regeneration.
     */
    public function fromLlmRegeneration(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => NarrativeSource::LlmRegeneration,
        ]);
    }
}

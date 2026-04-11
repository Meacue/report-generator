<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Models\Branch;
use App\Models\MatchResult;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchResult>
 */
class MatchResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id'        => Branch::factory(),
            'task_id'          => Task::factory(),
            'confidence_level' => fake()->randomElement(ConfidenceLevel::cases()),
            'resolved_by'      => fake()->randomElement(ResolvedBy::cases()),
            'resolved_at'      => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Automatically resolved match with high confidence.
     */
    public function auto(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::System,
            'resolved_at'      => now(),
        ]);
    }

    /**
     * Probable match requiring user confirmation.
     */
    public function probable(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_level' => ConfidenceLevel::Probable,
            'resolved_by'      => ResolvedBy::System,
            'resolved_at'      => now(),
        ]);
    }

    /**
     * Unmatched branch without a task.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_level' => ConfidenceLevel::None,
            'task_id'          => null,
            'resolved_by'      => ResolvedBy::System,
            'resolved_at'      => now(),
        ]);
    }
}

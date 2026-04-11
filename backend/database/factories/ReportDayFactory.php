<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Report\Enums\ReportDaySource;
use App\Models\Report;
use App\Models\ReportDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportDay>
 */
class ReportDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'date'      => fake()->dateTimeBetween('-3 months', 'now'),
            'narrative' => fake()->paragraph(2),
            'source'    => fake()->randomElement(ReportDaySource::cases()),
            'is_edited' => fake()->boolean(20),
        ];
    }

    /**
     * Day with narrative sourced from commits.
     */
    public function fromCommits(): static
    {
        return $this->state(fn (array $attributes) => [
            'source'    => ReportDaySource::Commits,
            'is_edited' => false,
        ]);
    }

    /**
     * Day with narrative sourced from Bitrix24 fallback (no commits).
     */
    public function fromBitrix24Fallback(): static
    {
        return $this->state(fn (array $attributes) => [
            'source'    => ReportDaySource::Bitrix24Fallback,
            'is_edited' => false,
        ]);
    }

    /**
     * Day with manually edited narrative.
     */
    public function manuallyEdited(): static
    {
        return $this->state(fn (array $attributes) => [
            'source'    => ReportDaySource::Manual,
            'is_edited' => true,
        ]);
    }
}

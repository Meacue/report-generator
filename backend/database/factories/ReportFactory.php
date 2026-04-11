<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Enums\ReportType;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateFrom = fake()->dateTimeBetween('-3 months', '-1 week');
        $dateTo = Carbon::instance($dateFrom)->addDays(fake()->numberBetween(1, 30));

        return [
            'type'      => fake()->randomElement(ReportType::cases()),
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'status'    => fake()->randomElement(ReportStatus::cases()),
        ];
    }

    /**
     * Weekly report in draft status.
     */
    public function weekly(): static
    {
        return $this->state(function (array $attributes) {
            $monday = Carbon::now()->startOfWeek();

            return [
                'type'      => ReportType::Weekly,
                'date_from' => $monday,
                'date_to'   => $monday->copy()->addDays(4),
            ];
        });
    }

    /**
     * Report in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Draft,
        ]);
    }

    /**
     * Report in generated status.
     */
    public function generated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Generated,
        ]);
    }

    /**
     * Report in exported status.
     */
    public function exported(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Exported,
        ]);
    }
}

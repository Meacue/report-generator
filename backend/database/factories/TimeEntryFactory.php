<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bitrix24\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $trackedAt = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'bitrix24_entry_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'bitrix24_task_id'  => fake()->numberBetween(1000, 99999),
            'bitrix24_user_id'  => (string) fake()->numberBetween(1, 999),
            'seconds'           => fake()->numberBetween(60, 14_400),
            'comment'           => fake()->optional()->sentence(),
            'tracked_at'        => $trackedAt,
            'source_created_at' => $trackedAt,
            'synced_at'         => now(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SyncLog>
 */
class SyncLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 month', 'now');
        $completedAt = Carbon::instance($startedAt)->addSeconds(fake()->numberBetween(5, 120));

        return [
            'source'        => fake()->randomElement(SyncSource::cases()),
            'status'        => SyncStatus::Success,
            'items_synced'  => fake()->numberBetween(0, 200),
            'error_message' => null,
            'started_at'    => $startedAt,
            'completed_at'  => $completedAt,
        ];
    }

    /**
     * Successful sync log.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => SyncStatus::Success,
            'error_message' => null,
        ]);
    }

    /**
     * Failed sync log with an error message.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => SyncStatus::Failed,
            'items_synced'  => 0,
            'error_message' => fake()->sentence(),
            'completed_at'  => null,
        ]);
    }

    /**
     * Sync log for GitLab source.
     */
    public function gitlab(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => SyncSource::GitLab,
        ]);
    }

    /**
     * Sync log for Bitrix24 source.
     */
    public function bitrix24(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => SyncSource::Bitrix24,
        ]);
    }
}

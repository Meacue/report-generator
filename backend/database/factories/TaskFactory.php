<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taskId = fake()->numberBetween(1000, 99999);
        $projectId = fake()->numberBetween(1, 50);

        return [
            'bitrix24_task_id'  => $taskId,
            'title'             => fake()->sentence(5, false),
            'status'            => fake()->randomElement(TaskStatus::cases()),
            'project_id'        => $projectId,
            'project_name'      => 'Project ' . fake()->bothify('??-##'),
            'bitrix24_url'      => '/company/personal/user/1/tasks/task/view/' . $taskId . '/',
            'status_changed_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'synced_at'         => now(),
        ];
    }

    /**
     * Task with completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'            => TaskStatus::Completed,
            'status_changed_at' => fake()->dateTimeBetween('-3 months', '-1 day'),
        ]);
    }

    /**
     * Task with in_progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InProgress,
        ]);
    }
}

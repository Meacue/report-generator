<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\GitLab\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['feature', 'bugfix', 'refactor', 'chore', 'hotfix'];
        $type = fake()->randomElement($types);
        $taskNumber = 'PRJ-' . fake()->numberBetween(100, 999);

        return [
            'gitlab_repo_id'       => fake()->numberBetween(1, 100),
            'branch_name'          => $type . '/' . $taskNumber . '_' . fake()->slug(3),
            'parsed_task_number'   => fake()->optional(0.6)->numerify('#####'),
            'parsed_date'          => fake()->optional(0.7)->date(),
            'parsed_parent_branch' => fake()->randomElement(['main', 'dev', 'staging']),
            'parsed_info'          => fake()->slug(3),
            'synced_at'            => now(),
        ];
    }

    /**
     * Branch with a parsed task number (numeric info segment).
     */
    public function withTask(): static
    {
        return $this->state(function (array $attributes) {
            $taskNumber = (string) fake()->numberBetween(10000, 99999);

            return [
                'parsed_task_number' => $taskNumber,
                'parsed_info'        => $taskNumber,
            ];
        });
    }

    /**
     * Branch without a date (legacy format).
     */
    public function withoutDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'parsed_date' => null,
        ]);
    }

    /**
     * Branch without a task number (descriptive info segment).
     */
    public function withoutTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'parsed_task_number' => null,
            'parsed_info'        => fake()->slug(2),
        ]);
    }
}

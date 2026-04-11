<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commit>
 */
class CommitFactory extends Factory
{
    protected $model = Commit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conventionalTypes = ['feat', 'fix', 'chore', 'refactor', 'docs', 'test', 'style', 'ci', 'perf'];
        $type = fake()->randomElement($conventionalTypes);
        $subject = fake()->sentence(4, false);
        $message = $type . ': ' . lcfirst($subject);

        return [
            'branch_id'         => Branch::factory(),
            'gitlab_commit_sha' => fake()->sha1(),
            'message'           => $message,
            'conventional_type' => $type,
            'author'            => fake()->name(),
            'committed_at'      => fake()->dateTimeBetween('-1 year', 'now'),
            'synced_at'         => now(),
        ];
    }

    /**
     * Commit of a specific conventional type.
     */
    public function ofType(string $type): static
    {
        return $this->state(function (array $attributes) use ($type) {
            $subject = fake()->sentence(4, false);

            return [
                'conventional_type' => $type,
                'message'           => $type . ': ' . lcfirst($subject),
            ];
        });
    }
}

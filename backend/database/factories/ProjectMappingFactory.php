<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMapping>
 */
class ProjectMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $repoId = fake()->numberBetween(1, 500);
        $projectId = fake()->numberBetween(1, 100);
        $suffix = fake()->bothify('??-##');

        return [
            'gitlab_repo_id'        => $repoId,
            'gitlab_repo_name'      => 'repo-' . fake()->slug(2),
            'bitrix24_project_id'   => $projectId,
            'bitrix24_project_name' => 'Project ' . $suffix,
        ];
    }
}

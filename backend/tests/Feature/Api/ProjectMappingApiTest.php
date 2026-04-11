<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Settings\Models\ProjectMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMappingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_mappings(): void
    {
        ProjectMapping::factory()->count(3)->create();

        $response = $this->getJson('/api/projects/mappings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'gitlab_repo_id',
                        'gitlab_repo_name',
                        'bitrix24_project_id',
                        'bitrix24_project_name',
                        'created_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_create_mapping(): void
    {
        $payload = [
            'gitlab_repo_id'        => 42,
            'gitlab_repo_name'      => 'my-repo',
            'bitrix24_project_id'   => 7,
            'bitrix24_project_name' => 'Project Alpha',
        ];

        $response = $this->postJson('/api/projects/mappings', $payload);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('project_mappings', [
            'gitlab_repo_id'        => 42,
            'gitlab_repo_name'      => 'my-repo',
            'bitrix24_project_id'   => 7,
            'bitrix24_project_name' => 'Project Alpha',
        ]);
    }

    public function test_update_mapping(): void
    {
        $mapping = ProjectMapping::factory()->create();

        $response = $this->putJson("/api/projects/mappings/{$mapping->id}", [
            'gitlab_repo_name' => 'updated-repo',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Updated']);

        $this->assertDatabaseHas('project_mappings', [
            'id'               => $mapping->id,
            'gitlab_repo_name' => 'updated-repo',
        ]);
    }

    public function test_delete_mapping(): void
    {
        $mapping = ProjectMapping::factory()->create();

        $response = $this->deleteJson("/api/projects/mappings/{$mapping->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Deleted']);

        $this->assertDatabaseMissing('project_mappings', [
            'id' => $mapping->id,
        ]);
    }

    public function test_create_mapping_validation(): void
    {
        $response = $this->postJson('/api/projects/mappings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'gitlab_repo_id',
                'gitlab_repo_name',
                'bitrix24_project_id',
                'bitrix24_project_name',
            ]);
    }
}

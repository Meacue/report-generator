<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectMappingRequest;
use App\Http\Requests\UpdateProjectMappingRequest;
use App\Models\ProjectMapping;
use Illuminate\Http\JsonResponse;

class ProjectMappingController extends Controller
{
    public function index(): JsonResponse
    {
        $mappings = ProjectMapping::all();

        return response()->json([
            'data' => $mappings->map(fn (ProjectMapping $m): array => [
                'id'                    => $m->id,
                'gitlab_repo_id'        => $m->gitlab_repo_id,
                'gitlab_repo_name'      => $m->gitlab_repo_name,
                'bitrix24_project_id'   => $m->bitrix24_project_id,
                'bitrix24_project_name' => $m->bitrix24_project_name,
                'created_at'            => $m->created_at?->toISOString(),
            ])->all(),
        ]);
    }

    public function store(StoreProjectMappingRequest $request): JsonResponse
    {
        /** @var array{gitlab_repo_id: int, gitlab_repo_name: string, bitrix24_project_id: int, bitrix24_project_name: string} $validated */
        $validated = $request->validated();

        $mapping = ProjectMapping::create($validated);

        return response()->json([
            'id'      => $mapping->id,
            'message' => 'Mapping created',
        ], 201);
    }

    public function update(UpdateProjectMappingRequest $request, ProjectMapping $mapping): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $mapping->update($validated);

        return response()->json(['message' => 'Updated']);
    }

    public function destroy(ProjectMapping $mapping): JsonResponse
    {
        $mapping->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

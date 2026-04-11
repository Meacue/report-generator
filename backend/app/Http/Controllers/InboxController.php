<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Inbox\AssignRequest;
use App\Http\Requests\Inbox\BulkAssignRequest;
use App\Http\Requests\Inbox\CreateTaskRequest;
use App\Http\Requests\Inbox\IgnoreRequest;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Bitrix24\Models\Task;
use App\Services\Inbox\InboxServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InboxController extends Controller
{
    public function __construct(
        private readonly InboxServiceInterface $inboxService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', '20');
        $filter = $request->query('filter');
        $sortDirection = $request->query('sort_direction', 'desc');

        $branches = $this->inboxService->getUnlinkedBranches(
            $perPage,
            is_string($filter) ? $filter : null,
            is_string($sortDirection) ? $sortDirection : 'desc',
        );

        /** @var LengthAwarePaginator<int, Branch> $paginator */
        $paginator = $branches;

        $data = [];
        foreach ($paginator->items() as $branch) {
            /** @var Branch $branch */
            $matchResult = $branch->matchResults->first();
            $lastCommit = $branch->commits->sortByDesc('committed_at')->first();

            $data[] = [
                'id'                 => $branch->id,
                'branch_name'        => $branch->branch_name,
                'gitlab_repo_id'     => $branch->gitlab_repo_id,
                'parsed_task_number' => $branch->parsed_task_number,
                'parsed_date'        => $branch->parsed_date?->format('Y-m-d'),
                'parsed_info'        => $branch->parsed_info,
                'confidence_level'   => $matchResult?->confidence_level->value,
                'commits_count'      => $branch->commits->count(),
                'last_commit'        => $lastCommit?->message,
                'synced_at'          => $branch->synced_at?->toISOString(),
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function assign(AssignRequest $request): JsonResponse
    {
        /** @var array{branch_id: int, task_id: int} $validated */
        $validated = $request->validated();

        $task = Task::where('bitrix24_task_id', $validated['task_id'])->firstOrFail();

        $this->inboxService->assign($validated['branch_id'], $task->id);

        return response()->json(['message' => 'Assigned']);
    }

    public function bulkAssign(BulkAssignRequest $request): JsonResponse
    {
        /** @var array{assignments: array<int, array{branch_id: int, task_id: int}>} $validated */
        $validated = $request->validated();

        $resolved = array_map(function (array $assignment): array {
            $task = Task::where('bitrix24_task_id', $assignment['task_id'])->firstOrFail();

            return [
                'branch_id' => $assignment['branch_id'],
                'task_id'   => $task->id,
            ];
        }, $validated['assignments']);

        $this->inboxService->bulkAssign($resolved);

        return response()->json(['message' => 'Bulk assigned']);
    }

    public function ignore(IgnoreRequest $request): JsonResponse
    {
        /** @var array{branch_id: int} $validated */
        $validated = $request->validated();

        $this->inboxService->ignore($validated['branch_id']);

        return response()->json(['message' => 'Ignored']);
    }

    public function createTask(CreateTaskRequest $request): JsonResponse
    {
        /** @var array{branch_id: int, title: string} $validated */
        $validated = $request->validated();

        $this->inboxService->createTaskAndAssign(
            $validated['branch_id'],
            $validated['title'],
        );

        return response()->json(['message' => 'Task created and assigned'], 201);
    }
}

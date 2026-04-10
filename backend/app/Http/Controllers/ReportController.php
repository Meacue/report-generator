<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NoDataException;
use App\Http\Requests\GenerateReportRequest;
use App\Http\Requests\UpdateNarrativeRequest;
use App\Models\Commit;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Task;
use App\Services\Narrative\NarrativeServiceInterface;
use App\Services\Report\PromptExportServiceInterface;
use App\Services\Report\ReportBuilderInterface;
use App\Services\Report\ReportExporterInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportBuilderInterface $builder,
        private readonly ReportExporterInterface $exporter,
        private readonly NarrativeServiceInterface $narrativeService,
        private readonly PromptExportServiceInterface $promptExportService,
    ) {
    }

    /**
     * List reports with pagination and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', '15');

        $sortDirection = $request->query('sort_direction', 'desc');
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $paginator = Report::orderBy('created_at', $sortDirection)->paginate($perPage);

        $data = [];
        /** @var Report $report */
        foreach ($paginator->items() as $report) {
            $data[] = [
                'id'         => $report->id,
                'type'       => $report->type->value,
                'date_from'  => $report->date_from->format('Y-m-d'),
                'date_to'    => $report->date_to->format('Y-m-d'),
                'status'     => $report->status->value,
                'created_at' => $report->created_at?->toIso8601String(),
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

    /**
     * Generate a new report.
     *
     * @throws NoDataException
     */
    public function generate(GenerateReportRequest $request): JsonResponse
    {
        /** @var array{type: string, date_from: string, date_to: string} $validated */
        $validated = $request->validated();

        $dateRange = $request->toDateRange();

        $commitsCount = Commit::query()
            ->whereBetween('committed_at', [$dateRange->from->startOfDay(), $dateRange->to->endOfDay()])
            ->count();

        $tasksCount = Task::query()
            ->whereBetween('status_changed_at', [$dateRange->from->startOfDay(), $dateRange->to->endOfDay()])
            ->count();

        if ($commitsCount === 0 && $tasksCount === 0) {
            throw new NoDataException();
        }

        $report = $this->builder->generate($validated['type'], $dateRange);

        $this->narrativeService->generateForReport($report);

        return response()->json(['data' => ['id' => $report->id]], 201);
    }

    /**
     * Get report preview with all days and tasks.
     */
    public function preview(Report $report): JsonResponse
    {
        $data = $this->builder->getPreview($report);

        return response()->json(['data' => $data]);
    }

    /**
     * Update day narrative.
     */
    public function updateDay(UpdateNarrativeRequest $request, Report $report, string $date): JsonResponse
    {
        /** @var array{narrative: string} $validated */
        $validated = $request->validated();

        $reportDay = $report->reportDays()->where('date', $date)->firstOrFail();

        $this->narrativeService->editDayNarrative($reportDay, $validated['narrative']);

        return response()->json(['message' => 'Updated']);
    }

    /**
     * Update task narrative.
     */
    public function updateTask(UpdateNarrativeRequest $request, Report $report, int $taskId): JsonResponse
    {
        /** @var array{narrative: string} $validated */
        $validated = $request->validated();

        $reportTask = $report->reportTasks()->where('task_id', $taskId)->firstOrFail();

        $this->narrativeService->editTaskNarrative($reportTask, $validated['narrative']);

        return response()->json(['message' => 'Updated']);
    }

    /**
     * Regenerate task narrative via LLM.
     */
    public function regenerateTask(Report $report, int $taskId): JsonResponse
    {
        $reportTask = $report->reportTasks()->where('task_id', $taskId)->firstOrFail();
        $updated = $this->narrativeService->regenerateTask($reportTask);

        return response()->json(['data' => $updated]);
    }

    /**
     * Regenerate day narrative via LLM.
     */
    public function regenerateDay(Report $report, string $date): JsonResponse
    {
        $reportDay = $report->reportDays()->where('date', $date)->firstOrFail();
        $updated = $this->narrativeService->regenerateDay($reportDay);

        return response()->json(['data' => $updated]);
    }

    /**
     * Undo last task narrative change.
     */
    public function undoTask(Report $report, int $taskId): JsonResponse
    {
        $reportTask = $report->reportTasks()->where('task_id', $taskId)->firstOrFail();
        $result = $this->narrativeService->undoTaskNarrative($reportTask);

        if ($result === null) {
            return response()->json(['message' => 'No history available'], 404);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Undo last day narrative change.
     */
    public function undoDay(Report $report, string $date): JsonResponse
    {
        $reportDay = $report->reportDays()->where('date', $date)->firstOrFail();
        $result = $this->narrativeService->undoDayNarrative($reportDay);

        if ($result === null) {
            return response()->json(['message' => 'No history available'], 404);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Export report summary + LLM system prompt as a .txt file.
     */
    public function exportPrompt(Report $report): Response
    {
        $content = $this->promptExportService->buildPromptFile($report);

        $filename = sprintf(
            'report-prompt-%s-%s.txt',
            $report->date_from->format('Y-m-d'),
            $report->date_to->format('Y-m-d'),
        );

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export report as .docx.
     */
    public function export(Report $report): BinaryFileResponse
    {
        $preview = $this->builder->getPreview($report);

        $settings = Setting::first();
        $developerName = $settings !== null ? ($settings->developer_name ?? 'Разработчик') : 'Разработчик';
        $developerPosition = $settings !== null ? ($settings->developer_position ?? '') : '';

        /** @var array<int, array{date: string, narrative: string|null, source: string, is_edited: bool, tasks: array<int, array{id: int|null, title: string, project_name: string|null, narrative: string|null, is_edited: bool}>}> $days */
        $days = $preview['days'];

        /** @var list<array{date: string, narrative: string, tasks: list<array{number: int, title: string, project_name: string, narrative: string}>}> $mappedDays */
        $mappedDays = [];

        foreach ($days as $day) {
            /** @var list<array{number: int, title: string, project_name: string, narrative: string}> $mappedTasks */
            $mappedTasks = [];
            $taskNumber = 1;

            foreach ($day['tasks'] as $task) {
                $mappedTasks[] = [
                    'number'       => $taskNumber,
                    'title'        => $task['title'],
                    'project_name' => $task['project_name'] ?? '',
                    'narrative'    => $task['narrative'] ?? '',
                ];
                $taskNumber++;
            }

            $mappedDays[] = [
                'date'  => $day['date'],
                'tasks' => $mappedTasks,
            ];
        }

        $reportData = [
            'type'               => $preview['type'],
            'developer_name'     => $developerName,
            'developer_position' => $developerPosition,
            'date_from'          => $preview['date_from'],
            'date_to'            => $preview['date_to'],
            'days'               => $mappedDays,
        ];

        $filePath = $this->exporter->export($reportData);

        $report->markAsExported();

        return response()->download($filePath);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Narrative\Actions\EditDayNarrative;
use App\Domain\Narrative\Actions\EditTaskNarrative;
use App\Domain\Narrative\Actions\RegenerateDayNarrative;
use App\Domain\Narrative\Actions\RegenerateTaskNarrative;
use App\Domain\Narrative\Actions\UndoDayNarrative;
use App\Domain\Narrative\Actions\UndoTaskNarrative;
use App\Domain\Narrative\Services\LlmConfigValidator;
use App\Domain\Matching\DTOs\UnclassifiedCommit;
use App\Domain\Matching\Queries\GetUnclassifiedCommitsForDateRange;
use App\Domain\Report\Actions\GenerateReport;
use App\Domain\Report\DTOs\ReportExportData;
use App\Domain\Report\DTOs\ReportExportDay;
use App\Domain\Report\DTOs\ReportExportMonthlyData;
use App\Domain\Report\DTOs\ReportExportTask;
use App\Domain\Report\DTOs\ReportExportUnclassifiedCommit;
use App\Domain\Report\DTOs\ReportPreview;
use App\Domain\Report\Queries\GetMonthlyReportData;
use App\Domain\Report\Queries\GetReportPreview;
use App\Domain\Report\Queries\HasDataForDateRange;
use App\Exceptions\NoDataException;
use App\Http\Requests\GenerateReportRequest;
use App\Http\Requests\UpdateNarrativeRequest;
use App\Domain\Report\Models\Report;
use App\Domain\Settings\Models\Setting;
use App\Domain\Report\Services\PromptExportServiceInterface;
use App\Domain\Report\Services\ReportExporterInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportExporterInterface $exporter,
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
    public function generate(
        GenerateReportRequest $request,
        GenerateReport $generateReport,
        HasDataForDateRange $hasData,
        LlmConfigValidator $llmConfigValidator,
    ): JsonResponse {
        $llmConfigValidator->validate();

        /** @var array{type: string, date_from: string, date_to: string} $validated */
        $validated = $request->validated();

        $dateRange = $request->toDateRange();

        if (! ($hasData)($dateRange)) {
            throw new NoDataException();
        }

        $report = $generateReport($validated['type'], $dateRange);

        return response()->json(['data' => ['id' => $report->id]], 201);
    }

    /**
     * Get report preview with all days and tasks.
     */
    public function preview(Report $report, GetReportPreview $getPreview): JsonResponse
    {
        $data = $getPreview($report);

        return response()->json(['data' => $data]);
    }

    /**
     * Update day narrative.
     */
    public function updateDay(UpdateNarrativeRequest $request, Report $report, string $date, EditDayNarrative $editDay): JsonResponse
    {
        /** @var array{narrative: string} $validated */
        $validated = $request->validated();

        $reportDay = $report->findDayOrFail($date);

        $editDay($reportDay, $validated['narrative']);

        return response()->json(['message' => 'Updated']);
    }

    /**
     * Update task narrative.
     */
    public function updateTask(UpdateNarrativeRequest $request, Report $report, int $taskId, EditTaskNarrative $editTask): JsonResponse
    {
        /** @var array{narrative: string} $validated */
        $validated = $request->validated();

        $reportTask = $report->findTaskOrFail($taskId);

        $editTask($reportTask, $validated['narrative']);

        return response()->json(['message' => 'Updated']);
    }

    /**
     * Regenerate task narrative via LLM.
     */
    public function regenerateTask(Report $report, int $taskId, RegenerateTaskNarrative $regenerateTask): JsonResponse
    {
        $reportTask = $report->findTaskOrFail($taskId);
        $updated = $regenerateTask($reportTask);

        return response()->json(['data' => $updated]);
    }

    /**
     * Regenerate day narrative via LLM.
     */
    public function regenerateDay(Report $report, string $date, RegenerateDayNarrative $regenerateDay): JsonResponse
    {
        $reportDay = $report->findDayOrFail($date);
        $updated = $regenerateDay($reportDay);

        return response()->json(['data' => $updated]);
    }

    /**
     * Undo last task narrative change.
     */
    public function undoTask(Report $report, int $taskId, UndoTaskNarrative $undoTask): JsonResponse
    {
        $reportTask = $report->findTaskOrFail($taskId);
        $result = $undoTask($reportTask);

        if ($result === null) {
            return response()->json(['message' => 'No history available'], 404);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Undo last day narrative change.
     */
    public function undoDay(Report $report, string $date, UndoDayNarrative $undoDay): JsonResponse
    {
        $reportDay = $report->findDayOrFail($date);
        $result = $undoDay($reportDay);

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
    public function export(
        Report $report,
        GetReportPreview $getPreview,
        GetMonthlyReportData $getMonthlyData,
        GetUnclassifiedCommitsForDateRange $getUnclassified,
    ): BinaryFileResponse {
        $report->guardExportable();

        $preview = $getPreview($report);

        $settings = Setting::first();
        $developerName = $settings !== null ? ($settings->developer_name ?? 'Разработчик') : 'Разработчик';
        $developerPosition = $settings !== null ? ($settings->developer_position ?? '') : '';

        $filePath = $preview->type === 'monthly'
            ? $this->buildMonthlyExport($report, $preview, $developerName, $developerPosition, $getMonthlyData, $getUnclassified)
            : $this->buildStandardExport($preview, $developerName, $developerPosition);

        $report->markAsExported();

        return response()->download($filePath);
    }

    private function buildMonthlyExport(
        Report $report,
        ReportPreview $preview,
        string $developerName,
        string $developerPosition,
        GetMonthlyReportData $getMonthlyData,
        GetUnclassifiedCommitsForDateRange $getUnclassified,
    ): string {
        $unclassified = $getUnclassified($report->getDateRange());

        $monthlyData = new ReportExportMonthlyData(
            developerName: $developerName,
            developerPosition: $developerPosition,
            dateFrom: $preview->dateFrom,
            dateTo: $preview->dateTo,
            days: $getMonthlyData($report),
            unclassifiedCommits: array_map(
                static fn (UnclassifiedCommit $c): ReportExportUnclassifiedCommit => new ReportExportUnclassifiedCommit(
                    repo: $c->repoName,
                    message: $c->message,
                    branch: $c->branchName,
                ),
                $unclassified,
            ),
        );

        return $this->exporter->exportMonthly($monthlyData);
    }

    private function buildStandardExport(
        ReportPreview $preview,
        string $developerName,
        string $developerPosition,
    ): string {
        /** @var list<ReportExportDay> $mappedDays */
        $mappedDays = [];

        foreach ($preview->days as $day) {
            /** @var list<ReportExportTask> $mappedTasks */
            $mappedTasks = [];
            $taskNumber = 1;

            foreach ($day->tasks as $task) {
                $mappedTasks[] = new ReportExportTask(
                    title: $task->title,
                    projectName: $task->projectName ?? '',
                    narrative: $task->narrative ?? '',
                    number: $taskNumber,
                );
                $taskNumber++;
            }

            $mappedDays[] = new ReportExportDay(
                date: $day->date,
                tasks: $mappedTasks,
            );
        }

        $reportData = new ReportExportData(
            type: $preview->type,
            developerName: $developerName,
            developerPosition: $developerPosition,
            dateFrom: $preview->dateFrom,
            dateTo: $preview->dateTo,
            days: $mappedDays,
        );

        return $this->exporter->exportStandard($reportData);
    }
}

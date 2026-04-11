<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\DTOs\DayCommitsNarrativeRequest;
use App\Domain\Narrative\DTOs\DayFallbackRequest;
use App\Domain\Narrative\DTOs\TaskNarrativeRequest;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\ValueObjects\Narrative;
use App\Services\LLM\LlmProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class GenerateNarrativesForReport
{
    public function __construct(
        private LlmProviderInterface $llmProvider,
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(Report $report): void
    {
        $report->load(['reportTasks.task.matchResults.branch.commits', 'reportDays.reportDayTasks.reportTask.task']);
        $systemPrompt = $this->support->getSystemPrompt();

        $this->generateDayTaskNarratives($report, $systemPrompt);
        $this->generateDayLevelNarratives($report, $systemPrompt);
        $this->generateGlobalTaskNarratives($report, $systemPrompt);

        $report->update(['status' => ReportStatus::Generated]);
    }

    private function generateDayTaskNarratives(Report $report, ?string $systemPrompt): void
    {
        /** @var array<int, list<string>> $previousNarrativesByTask */
        $previousNarrativesByTask = [];

        foreach ($report->reportDays as $reportDay) {
            foreach ($reportDay->reportDayTasks as $reportDayTask) {
                $this->generateDayTaskNarrative(
                    $reportDayTask,
                    $reportDay,
                    $systemPrompt,
                    $previousNarrativesByTask[$reportDayTask->report_task_id] ?? [],
                );
                if ($reportDayTask->narrative !== null && $reportDayTask->narrative !== Narrative::placeholder()->text) {
                    $previousNarrativesByTask[$reportDayTask->report_task_id][] = $reportDayTask->narrative;
                }
            }
        }
    }

    private function generateDayLevelNarratives(Report $report, ?string $systemPrompt): void
    {
        foreach ($report->reportDays as $reportDay) {
            if ($reportDay->source === ReportDaySource::Bitrix24Fallback) {
                $this->generateDayFallbackNarrative($reportDay, $report, $systemPrompt);

                continue;
            }

            if ($reportDay->source === ReportDaySource::Commits && $reportDay->reportDayTasks->isEmpty()) {
                $this->generateDayCommitsNarrative($reportDay, $systemPrompt);
            }
        }
    }

    private function generateGlobalTaskNarratives(Report $report, ?string $systemPrompt): void
    {
        foreach ($report->reportTasks as $reportTask) {
            $this->generateTaskNarrative($reportTask, $systemPrompt);
        }
    }

    private function generateTaskNarrative(ReportTask $reportTask, ?string $systemPrompt): void
    {
        $commits = $this->support->getCommitMessagesForTask($reportTask);
        $task = $reportTask->task;
        $enrichment = $this->support->isEnrichmentEnabled() ? $this->support->getEnrichmentDataForTask($reportTask) : null;

        $request = new TaskNarrativeRequest(
            taskTitle: $task->title ?? '',
            projectName: $reportTask->project_name ?? $task->project_name ?? '',
            commits: $commits,
            systemPrompt: $systemPrompt,
            mrTitle: $enrichment['mrTitle'] ?? null,
            mrDescription: $enrichment['mrDescription'] ?? null,
            totalAdditions: $enrichment['totalAdditions'] ?? null,
            totalDeletions: $enrichment['totalDeletions'] ?? null,
            changedFiles: $enrichment['changedFiles'] ?? [],
        );

        try {
            $response = $this->llmProvider->generateNarrative($request);
            $reportTask->update(['narrative' => $response->narrative]);
        } catch (Throwable $e) {
            Log::error('Failed to generate task narrative', [
                'report_task_id' => $reportTask->id,
                'error'          => $e->getMessage(),
            ]);
            $reportTask->update(['narrative' => Narrative::placeholder()->text]);
        }
    }

    private function generateDayFallbackNarrative(
        ReportDay $reportDay,
        Report $report,
        ?string $systemPrompt,
    ): void {
        $taskTitles = $this->support->extractTaskTitles($report);

        $request = new DayFallbackRequest(
            date: $reportDay->date->toDateString(),
            taskTitles: $taskTitles,
            systemPrompt: $systemPrompt,
        );

        try {
            $response = $this->llmProvider->generateDayFallback($request);
            $reportDay->update(['narrative' => $response->narrative]);
        } catch (Throwable $e) {
            Log::error('Failed to generate day fallback narrative', [
                'report_day_id' => $reportDay->id,
                'error'         => $e->getMessage(),
            ]);
            $reportDay->update(['narrative' => Narrative::placeholder()->text]);
        }
    }

    private function generateDayCommitsNarrative(ReportDay $reportDay, ?string $systemPrompt): void
    {
        $commits = $this->support->getCommitMessagesForDate($reportDay);

        if ($commits === []) {
            return;
        }

        $request = new DayCommitsNarrativeRequest(
            date: $reportDay->date->toDateString(),
            commits: $commits,
            systemPrompt: $systemPrompt,
        );

        try {
            $response = $this->llmProvider->generateDayFromCommits($request);
            $reportDay->update(['narrative' => $response->narrative]);
        } catch (Throwable $e) {
            Log::error('Failed to generate day commits narrative', [
                'report_day_id' => $reportDay->id,
                'error'         => $e->getMessage(),
            ]);
            $reportDay->update(['narrative' => Narrative::placeholder()->text]);
        }
    }

    /**
     * @param  array<int, string>  $previousNarratives
     */
    private function generateDayTaskNarrative(
        ReportDayTask $reportDayTask,
        ReportDay $reportDay,
        ?string $systemPrompt,
        array $previousNarratives = [],
    ): void {
        /** @var ReportTask|null $reportTask */
        $reportTask = $reportDayTask->reportTask;

        if ($reportTask === null) {
            return;
        }

        $task = $reportTask->task;

        if ($task === null) {
            return;
        }

        $commits = $this->support->getCommitMessagesForDayTask($reportDay, $reportTask);

        if ($commits === []) {
            $this->fallbackToGlobalNarrative($reportDayTask, $reportTask);

            return;
        }

        $enrichment = $this->support->isEnrichmentEnabled() ? $this->support->getEnrichmentDataForTask($reportTask) : null;

        $request = new TaskNarrativeRequest(
            taskTitle: $task->title ?? '',
            projectName: $reportTask->project_name ?? $task->project_name ?? '',
            commits: $commits,
            systemPrompt: $systemPrompt,
            mrTitle: $enrichment['mrTitle'] ?? null,
            mrDescription: $enrichment['mrDescription'] ?? null,
            totalAdditions: $enrichment['totalAdditions'] ?? null,
            totalDeletions: $enrichment['totalDeletions'] ?? null,
            changedFiles: $enrichment['changedFiles'] ?? [],
            previousNarratives: $previousNarratives,
        );

        try {
            $response = $this->llmProvider->generateNarrative($request);
            $reportDayTask->update(['narrative' => $response->narrative]);
        } catch (Throwable $e) {
            Log::error('Failed to generate day-task narrative', [
                'report_day_task_id' => $reportDayTask->id,
                'error'              => $e->getMessage(),
            ]);
            $this->fallbackToGlobalNarrative($reportDayTask, $reportTask);
        }
    }

    private function fallbackToGlobalNarrative(ReportDayTask $reportDayTask, ReportTask $reportTask): void
    {
        $reportDayTask->update(['narrative' => $reportTask->narrative]);
    }
}

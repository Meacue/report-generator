<?php

declare(strict_types=1);

namespace App\Services\Narrative;

use App\DTOs\DayCommitsNarrativeRequest;
use App\DTOs\DayFallbackRequest;
use App\DTOs\TaskNarrativeRequest;
use App\Enums\NarrativeSource;
use App\Enums\ReportDaySource;
use App\Enums\ReportStatus;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\MatchResult;
use App\Models\NarrativeHistory;
use App\Models\Report;
use App\Models\ReportDay;
use App\Models\ReportDayTask;
use App\Models\ReportTask;
use App\Models\Setting;
use App\Services\LLM\LlmProviderInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class NarrativeService implements NarrativeServiceInterface
{
    private const string PLACEHOLDER = '[Не удалось сгенерировать описание. Отредактируйте вручную.]';

    private const int MAX_HISTORY_ENTRIES = 5;

    public function __construct(
        private readonly LlmProviderInterface $llmProvider,
    ) {
    }

    public function generateForReport(Report $report): void
    {
        $report->load(['reportTasks.task.matchResults.branch.commits', 'reportDays.reportDayTasks.reportTask.task']);
        $systemPrompt = $this->getSystemPrompt();

        $this->generateDayTaskNarratives($report, $systemPrompt);
        $this->generateDayLevelNarratives($report, $systemPrompt);
        $this->generateGlobalTaskNarratives($report, $systemPrompt);

        $report->update(['status' => ReportStatus::Generated]);
    }

    public function regenerateTask(ReportTask $reportTask): ReportTask
    {
        $this->saveHistory($reportTask, NarrativeSource::LlmRegeneration);

        $commits = $this->getCommitMessagesForTask($reportTask);
        $systemPrompt = $this->getSystemPrompt();
        $task = $reportTask->task;
        $enrichment = $this->isEnrichmentEnabled() ? $this->getEnrichmentDataForTask($reportTask) : null;

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
            $reportTask->update([
                'narrative' => $response->narrative,
                'is_edited' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to regenerate task narrative', [
                'report_task_id' => $reportTask->id,
                'error'          => $e->getMessage(),
            ]);
            $reportTask->update([
                'narrative' => self::PLACEHOLDER,
                'is_edited' => false,
            ]);
        }

        return $reportTask->refresh();
    }

    public function regenerateDay(ReportDay $reportDay): ReportDay
    {
        $this->saveHistory($reportDay, NarrativeSource::LlmRegeneration);

        $reportDay->load('report.reportTasks.task');

        /** @var Report|null $report */
        $report = $reportDay->report;

        if ($report === null) {
            return $reportDay->refresh();
        }

        $systemPrompt = $this->getSystemPrompt();

        if ($reportDay->source === ReportDaySource::Commits && ! $this->dayHasLinkedTasks($reportDay)) {
            return $this->regenerateDayFromCommits($reportDay, $systemPrompt);
        }

        $taskTitles = $this->extractTaskTitles($report);

        $request = new DayFallbackRequest(
            date: $reportDay->date->toDateString(),
            taskTitles: $taskTitles,
            systemPrompt: $systemPrompt,
        );

        try {
            $response = $this->llmProvider->generateDayFallback($request);
            $reportDay->update([
                'narrative' => $response->narrative,
                'is_edited' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to regenerate day narrative', [
                'report_day_id' => $reportDay->id,
                'error'         => $e->getMessage(),
            ]);
            $reportDay->update([
                'narrative' => self::PLACEHOLDER,
                'is_edited' => false,
            ]);
        }

        return $reportDay->refresh();
    }

    public function editTaskNarrative(ReportTask $reportTask, string $newNarrative): ReportTask
    {
        $this->saveHistory($reportTask, NarrativeSource::ManualEdit);

        $reportTask->update([
            'narrative' => $newNarrative,
            'is_edited' => true,
        ]);

        return $reportTask->refresh();
    }

    public function editDayNarrative(ReportDay $reportDay, string $newNarrative): ReportDay
    {
        $this->saveHistory($reportDay, NarrativeSource::ManualEdit);

        $reportDay->update([
            'narrative' => $newNarrative,
            'is_edited' => true,
        ]);

        return $reportDay->refresh();
    }

    public function undoTaskNarrative(ReportTask $reportTask): ?ReportTask
    {
        return $this->undoNarrative($reportTask);
    }

    public function undoDayNarrative(ReportDay $reportDay): ?ReportDay
    {
        return $this->undoNarrative($reportDay);
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
                if ($reportDayTask->narrative !== null && $reportDayTask->narrative !== self::PLACEHOLDER) {
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

    /**
     * @template T of ReportTask|ReportDay
     *
     * @param  T  $model
     * @return T|null
     */
    private function undoNarrative(ReportTask|ReportDay $model): ReportTask|ReportDay|null
    {
        /** @var MorphMany<NarrativeHistory, ReportTask|ReportDay> $relation */
        $relation = $model->narrativeHistory();

        $latestHistory = $relation
            ->orderByDesc('changed_at')
            ->first();

        if ($latestHistory === null) {
            return null;
        }

        $model->update([
            'narrative' => $latestHistory->previous_narrative,
        ]);

        $latestHistory->delete();

        return $model->refresh();
    }

    private function dayHasLinkedTasks(ReportDay $reportDay): bool
    {
        $commits = Commit::whereDate('committed_at', $reportDay->date->toDateString())->get();

        if ($commits->isEmpty()) {
            return false;
        }

        /** @var list<int> $branchIds */
        $branchIds = $commits->pluck('branch_id')->unique()->filter()->values()->all();

        if ($branchIds === []) {
            return false;
        }

        return MatchResult::whereIn('branch_id', $branchIds)
            ->whereNotNull('task_id')
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function getCommitMessagesForDate(ReportDay $reportDay): array
    {
        /** @var array<int, string> */
        return Commit::whereDate('committed_at', $reportDay->date->toDateString())
            ->pluck('message')
            ->all();
    }

    private function generateDayCommitsNarrative(ReportDay $reportDay, ?string $systemPrompt): void
    {
        $commits = $this->getCommitMessagesForDate($reportDay);

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
            $reportDay->update(['narrative' => self::PLACEHOLDER]);
        }
    }

    private function regenerateDayFromCommits(ReportDay $reportDay, ?string $systemPrompt): ReportDay
    {
        $commits = $this->getCommitMessagesForDate($reportDay);

        if ($commits === []) {
            return $reportDay->refresh();
        }

        $request = new DayCommitsNarrativeRequest(
            date: $reportDay->date->toDateString(),
            commits: $commits,
            systemPrompt: $systemPrompt,
        );

        try {
            $response = $this->llmProvider->generateDayFromCommits($request);
            $reportDay->update([
                'narrative' => $response->narrative,
                'is_edited' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to regenerate day commits narrative', [
                'report_day_id' => $reportDay->id,
                'error'         => $e->getMessage(),
            ]);
            $reportDay->update([
                'narrative' => self::PLACEHOLDER,
                'is_edited' => false,
            ]);
        }

        return $reportDay->refresh();
    }

    private function generateTaskNarrative(ReportTask $reportTask, ?string $systemPrompt): void
    {
        $commits = $this->getCommitMessagesForTask($reportTask);
        $task = $reportTask->task;
        $enrichment = $this->isEnrichmentEnabled() ? $this->getEnrichmentDataForTask($reportTask) : null;

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
            $reportTask->update(['narrative' => self::PLACEHOLDER]);
        }
    }

    private function generateDayFallbackNarrative(
        ReportDay $reportDay,
        Report $report,
        ?string $systemPrompt,
    ): void {
        $taskTitles = $this->extractTaskTitles($report);

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
            $reportDay->update(['narrative' => self::PLACEHOLDER]);
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

        $commits = $this->getCommitMessagesForDayTask($reportDay, $reportTask);

        if ($commits === []) {
            $this->fallbackToGlobalNarrative($reportDayTask, $reportTask);

            return;
        }

        $enrichment = $this->isEnrichmentEnabled() ? $this->getEnrichmentDataForTask($reportTask) : null;

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

    /**
     * Get commit messages for a specific task on a specific day.
     *
     * @return array<int, string>
     */
    private function getCommitMessagesForDayTask(ReportDay $reportDay, ReportTask $reportTask): array
    {
        $task = $reportTask->task;

        if ($task === null) {
            return [];
        }

        $task->loadMissing('matchResults');

        /** @var list<int> $branchIds */
        $branchIds = $task->matchResults
            ->pluck('branch_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($branchIds === []) {
            return [];
        }

        /** @var array<int, string> */
        return Commit::whereDate('committed_at', $reportDay->date->toDateString())
            ->whereIn('branch_id', $branchIds)
            ->pluck('message')
            ->all();
    }

    /**
     * @return Collection<int, Branch>
     */
    private function collectBranches(ReportTask $reportTask): Collection
    {
        $task = $reportTask->task;

        if ($task === null) {
            return new Collection();
        }

        $task->loadMissing('matchResults.branch.commits');

        return $task->matchResults
            ->map(fn ($mr) => $mr->branch)
            ->filter()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function getCommitMessagesForTask(ReportTask $reportTask): array
    {
        $branches = $this->collectBranches($reportTask);

        /** @var list<string> $messages */
        $messages = [];

        foreach ($branches as $branch) {
            foreach ($branch->commits as $commit) {
                $messages[] = $commit->message;
            }
        }

        return $messages;
    }

    /**
     * @return array{mrTitle: string|null, mrDescription: string|null, totalAdditions: int|null, totalDeletions: int|null, changedFiles: array<int, string>}
     */
    private function getEnrichmentDataForTask(ReportTask $reportTask): array
    {
        $branches = $this->collectBranches($reportTask);

        $mrTitle = null;
        $mrDescription = null;
        $totalAdditions = 0;
        $totalDeletions = 0;
        /** @var list<string> $changedFiles */
        $changedFiles = [];

        foreach ($branches as $branch) {
            if ($mrTitle === null && $branch->mr_title !== null) {
                $mrTitle = $branch->mr_title;
            }
            if ($mrDescription === null && $branch->mr_description !== null) {
                $mrDescription = $branch->mr_description;
            }
            $totalAdditions += $branch->mr_additions ?? 0;
            $totalDeletions += $branch->mr_deletions ?? 0;
            if (is_array($branch->mr_changed_files)) {
                $changedFiles = array_merge($changedFiles, $branch->mr_changed_files);
            }
        }

        return [
            'mrTitle'        => $mrTitle,
            'mrDescription'  => $mrDescription,
            'totalAdditions' => $totalAdditions > 0 ? $totalAdditions : null,
            'totalDeletions' => $totalDeletions > 0 ? $totalDeletions : null,
            'changedFiles'   => array_values(array_unique(array_slice($changedFiles, 0, 20))),
        ];
    }

    private function isEnrichmentEnabled(): bool
    {
        /** @var Setting|null $setting */
        $setting = Setting::query()->first();

        return $setting !== null ? ($setting->enriched_prompt_enabled ?? true) : true;
    }

    /**
     * @return array<int, string>
     */
    private function extractTaskTitles(Report $report): array
    {
        /** @var array<int, string> $titles */
        $titles = $report->reportTasks
            ->map(fn (ReportTask $rt): string => $rt->task->title ?? '')
            ->filter(fn (string $title): bool => $title !== '')
            ->values()
            ->all();

        return $titles;
    }

    private function saveHistory(ReportTask|ReportDay $model, NarrativeSource $source): void
    {
        $model->narrativeHistory()->create([
            'previous_narrative' => $model->narrative,
            'changed_at'         => now(),
            'source'             => $source,
        ]);

        $this->pruneHistory($model);
    }

    private function pruneHistory(ReportTask|ReportDay $model): void
    {
        /** @var MorphMany<NarrativeHistory, ReportTask|ReportDay> $relation */
        $relation = $model->narrativeHistory();

        $historyCount = $relation->count();

        if ($historyCount <= self::MAX_HISTORY_ENTRIES) {
            return;
        }

        $idsToKeep = $relation
            ->orderByDesc('changed_at')
            ->limit(self::MAX_HISTORY_ENTRIES)
            ->pluck('id');

        $relation
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    private function getSystemPrompt(): ?string
    {
        return Setting::query()->first()?->llm_system_prompt;
    }
}

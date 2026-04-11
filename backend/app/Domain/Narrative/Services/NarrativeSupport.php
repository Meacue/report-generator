<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Services;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Settings\Models\Setting;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

final class NarrativeSupport
{
    private const int MAX_HISTORY_ENTRIES = 5;

    public function saveHistory(ReportTask|ReportDay $model, NarrativeSource $source): void
    {
        $model->narrativeHistory()->create([
            'previous_narrative' => $model->narrative,
            'changed_at'         => now(),
            'source'             => $source,
        ]);

        $this->pruneHistory($model);
    }

    public function pruneHistory(ReportTask|ReportDay $model): void
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

    public function getSystemPrompt(): ?string
    {
        return Setting::query()->first()?->llm_system_prompt;
    }

    /**
     * @return array<int, string>
     */
    public function getCommitMessagesForTask(ReportTask $reportTask): array
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
     * @return array<int, string>
     */
    public function getCommitMessagesForDate(ReportDay $reportDay): array
    {
        /** @var array<int, string> */
        return Commit::whereDate('committed_at', $reportDay->date->toDateString())
            ->pluck('message')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getCommitMessagesForDayTask(ReportDay $reportDay, ReportTask $reportTask): array
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
    public function collectBranches(ReportTask $reportTask): Collection
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
     * @return array{mrTitle: string|null, mrDescription: string|null, totalAdditions: int|null, totalDeletions: int|null, changedFiles: array<int, string>}
     */
    public function getEnrichmentDataForTask(ReportTask $reportTask): array
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

    public function isEnrichmentEnabled(): bool
    {
        /** @var Setting|null $setting */
        $setting = Setting::query()->first();

        return $setting !== null ? ($setting->enriched_prompt_enabled ?? true) : true;
    }

    /**
     * @return array<int, string>
     */
    public function extractTaskTitles(Report $report): array
    {
        /** @var array<int, string> $titles */
        $titles = $report->reportTasks
            ->map(fn (ReportTask $rt): string => $rt->task->title ?? '')
            ->filter(fn (string $title): bool => $title !== '')
            ->values()
            ->all();

        return $titles;
    }

    /**
     * @template T of ReportTask|ReportDay
     *
     * @param  T  $model
     * @return T|null
     */
    public function undoNarrative(ReportTask|ReportDay $model): ReportTask|ReportDay|null
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

    public function dayHasLinkedTasks(ReportDay $reportDay): bool
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
}

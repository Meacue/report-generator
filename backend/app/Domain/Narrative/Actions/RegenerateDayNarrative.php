<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\DTOs\DayCommitsNarrativeRequest;
use App\Domain\Narrative\DTOs\DayFallbackRequest;
use App\Domain\Narrative\Events\NarrativeRegenerated;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\ValueObjects\Narrative;
use App\Services\LLM\LlmProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RegenerateDayNarrative
{
    public function __construct(
        private LlmProviderInterface $llmProvider,
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(ReportDay $reportDay): ReportDay
    {
        $previousNarrative = $reportDay->narrative ?? '';

        $reportDay->load('report.reportTasks.task');

        /** @var Report|null $report */
        $report = $reportDay->report;

        if ($report === null) {
            return $reportDay->refresh();
        }

        $systemPrompt = $this->support->getSystemPrompt();

        if ($reportDay->source === ReportDaySource::Commits && ! $this->support->dayHasLinkedTasks($reportDay)) {
            $regenerated = $this->regenerateDayFromCommits($reportDay, $systemPrompt);

            if ($regenerated) {
                NarrativeRegenerated::dispatch($reportDay, $previousNarrative);
            }

            return $reportDay->refresh();
        }

        $taskTitles = $this->support->extractTaskTitles($report);

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
                'narrative' => Narrative::placeholder()->text,
                'is_edited' => false,
            ]);
        }

        NarrativeRegenerated::dispatch($reportDay, $previousNarrative);

        return $reportDay->refresh();
    }

    private function regenerateDayFromCommits(ReportDay $reportDay, ?string $systemPrompt): bool
    {
        $commits = $this->support->getCommitMessagesForDate($reportDay);

        if ($commits === []) {
            return false;
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
                'narrative' => Narrative::placeholder()->text,
                'is_edited' => false,
            ]);
        }

        return true;
    }
}

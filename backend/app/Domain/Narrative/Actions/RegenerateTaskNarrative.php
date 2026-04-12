<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\DTOs\TaskNarrativeRequest;
use App\Domain\Narrative\Events\NarrativeRegenerated;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\ValueObjects\Narrative;
use App\Domain\Narrative\Services\LlmProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RegenerateTaskNarrative
{
    public function __construct(
        private LlmProviderInterface $llmProvider,
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(ReportTask $reportTask): ReportTask
    {
        $previousNarrative = $reportTask->narrative ?? '';

        $commits = $this->support->getCommitMessagesForTask($reportTask);
        $systemPrompt = $this->support->getSystemPrompt();
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
                'narrative' => Narrative::placeholder()->text,
                'is_edited' => false,
            ]);
        }

        NarrativeRegenerated::dispatch($reportTask, $previousNarrative);

        return $reportTask->refresh();
    }
}

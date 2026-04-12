<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Listeners;

use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Narrative\Events\NarrativeRegenerated;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final readonly class SaveNarrativeHistory
{
    private const int MAX_HISTORY_ENTRIES = 5;

    public function handle(NarrativeEdited|NarrativeRegenerated $event): void
    {
        $event->narratable->narrativeHistory()->create([
            'previous_narrative' => $event->previousNarrative,
            'changed_at'         => now(),
            'source'             => $event->source(),
        ]);

        $this->pruneHistory($event->narratable);
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
}

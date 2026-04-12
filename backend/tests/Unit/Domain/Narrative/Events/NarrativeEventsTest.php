<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Events;

use App\Domain\Narrative\Actions\EditDayNarrative;
use App\Domain\Narrative\Actions\EditTaskNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Narrative\Events\NarrativeRegenerated;
use App\Domain\Narrative\Listeners\SaveNarrativeHistory;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class NarrativeEventsTest extends TestCase
{
    use RefreshDatabase;

    private SaveNarrativeHistory $listener;

    // -------------------------------------------------------------------------
    // Dispatch assertions
    // -------------------------------------------------------------------------

    public function test_edit_task_narrative_dispatches_event(): void
    {
        // GIVEN — a report task exists
        Event::fake([NarrativeEdited::class]);
        $reportTask = ReportTask::factory()->create(['narrative' => 'original text']);
        $action = new EditTaskNarrative();

        // WHEN — the EditTaskNarrative action is invoked
        $action($reportTask, 'new text');

        // THEN — a NarrativeEdited event is dispatched
        Event::assertDispatched(NarrativeEdited::class);
    }

    public function test_edit_day_narrative_dispatches_event(): void
    {
        // GIVEN — a report day exists
        Event::fake([NarrativeEdited::class]);
        $reportDay = ReportDay::factory()->create(['narrative' => 'original text']);
        $action = new EditDayNarrative();

        // WHEN — the EditDayNarrative action is invoked
        $action($reportDay, 'new text');

        // THEN — a NarrativeEdited event is dispatched
        Event::assertDispatched(NarrativeEdited::class);
    }

    // -------------------------------------------------------------------------
    // SaveNarrativeHistory listener — NarrativeEdited
    // -------------------------------------------------------------------------

    public function test_save_narrative_history_listener_creates_history_on_edit(): void
    {
        // GIVEN — a report task with a known previous narrative
        $previousNarrative = 'previous text for edit';
        $reportTask = ReportTask::factory()->create(['narrative' => 'current text']);
        $event = new NarrativeEdited($reportTask, $previousNarrative);

        // WHEN — the listener handles the NarrativeEdited event
        $this->listener->handle($event);

        // THEN — a narrative_history row is created with the correct source
        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $previousNarrative,
            'source'             => NarrativeSource::ManualEdit->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // SaveNarrativeHistory listener — NarrativeRegenerated
    // -------------------------------------------------------------------------

    public function test_save_narrative_history_listener_creates_history_on_regeneration(): void
    {
        // GIVEN — a report task with a known previous narrative
        $previousNarrative = 'previous text before regeneration';
        $reportTask = ReportTask::factory()->create(['narrative' => 'regenerated text']);
        $event = new NarrativeRegenerated($reportTask, $previousNarrative);

        // WHEN — the listener handles the NarrativeRegenerated event
        $this->listener->handle($event);

        // THEN — a narrative_history row is created with source LlmRegeneration
        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $previousNarrative,
            'source'             => NarrativeSource::LlmRegeneration->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // SaveNarrativeHistory listener — pruning
    // -------------------------------------------------------------------------

    public function test_save_narrative_history_listener_prunes_old_entries(): void
    {
        // GIVEN — a report task already has 6 history entries (one above the max of 5)
        $reportTask = ReportTask::factory()->create(['narrative' => 'current text']);
        NarrativeHistory::factory()->count(6)->create([
            'narratable_type' => ReportTask::MORPH_ALIAS,
            'narratable_id'   => $reportTask->id,
        ]);
        $this->assertSame(6, $reportTask->narrativeHistory()->count());

        $event = new NarrativeEdited($reportTask, 'text before pruning trigger');

        // WHEN — the listener handles another NarrativeEdited event (total becomes 7)
        $this->listener->handle($event);

        // THEN — the oldest entry is pruned and only 5 entries remain
        $this->assertSame(5, $reportTask->narrativeHistory()->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new SaveNarrativeHistory();
    }
}

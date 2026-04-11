<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\UndoTaskNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UndoTaskNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private UndoTaskNarrative $action;

    public function test_restores_previous(): void
    {
        $previousNarrative = 'This was the previous narrative.';
        $reportTask = ReportTask::factory()->create(['narrative' => 'Current narrative.']);

        NarrativeHistory::factory()->create([
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $previousNarrative,
            'changed_at'         => now(),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        $result = ($this->action)($reportTask);

        $this->assertNotNull($result);
        $this->assertEquals($previousNarrative, $result->narrative);
    }

    public function test_deletes_history_entry(): void
    {
        $reportTask = ReportTask::factory()->create();

        $historyEntry = NarrativeHistory::factory()->create([
            'narratable_type' => ReportTask::MORPH_ALIAS,
            'narratable_id'   => $reportTask->id,
            'changed_at'      => now(),
        ]);

        ($this->action)($reportTask);

        $this->assertDatabaseMissing('narrative_history', ['id' => $historyEntry->id]);
    }

    public function test_returns_null_when_no_history(): void
    {
        $reportTask = ReportTask::factory()->create();

        $result = ($this->action)($reportTask);

        $this->assertNull($result);
    }

    public function test_uses_latest_history_entry_by_changed_at(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Current.']);

        NarrativeHistory::factory()->create([
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => 'Older narrative.',
            'changed_at'         => now()->subMinutes(10),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        NarrativeHistory::factory()->create([
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => 'Most recent narrative.',
            'changed_at'         => now()->subMinutes(1),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        $result = ($this->action)($reportTask);

        $this->assertNotNull($result);
        $this->assertEquals('Most recent narrative.', $result->narrative);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UndoTaskNarrative(new NarrativeSupport());
    }
}

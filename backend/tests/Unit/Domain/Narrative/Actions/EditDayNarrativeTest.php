<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\EditDayNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Report\Models\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class EditDayNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private EditDayNarrative $action;

    public function test_saves_history(): void
    {
        $oldNarrative = 'Original day narrative.';
        $reportDay = ReportDay::factory()->fromCommits()->create(['narrative' => $oldNarrative]);

        ($this->action)($reportDay, 'New manual day narrative.');

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => ReportDay::MORPH_ALIAS,
            'narratable_id'      => $reportDay->id,
            'previous_narrative' => $oldNarrative,
            'source'             => NarrativeSource::ManualEdit->value,
        ]);
    }

    public function test_sets_is_edited_to_true(): void
    {
        $reportDay = ReportDay::factory()->fromCommits()->create();

        $result = ($this->action)($reportDay, 'Manually written day narrative.');

        $this->assertTrue($result->is_edited);
    }

    public function test_dispatches_narrative_edited_event(): void
    {
        Event::fake([NarrativeEdited::class]);

        $oldNarrative = 'Original day text.';
        $reportDay = ReportDay::factory()->fromCommits()->create(['narrative' => $oldNarrative]);

        ($this->action)($reportDay, 'New day text.');

        Event::assertDispatched(NarrativeEdited::class, function (NarrativeEdited $event) use ($reportDay, $oldNarrative): bool {
            return $event->narratable->is($reportDay) && $event->previousNarrative === $oldNarrative;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new EditDayNarrative();
    }
}

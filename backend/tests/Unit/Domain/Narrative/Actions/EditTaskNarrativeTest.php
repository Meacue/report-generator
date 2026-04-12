<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\EditTaskNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class EditTaskNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private EditTaskNarrative $action;

    public function test_saves_history(): void
    {
        $oldNarrative = 'Original narrative text.';
        $reportTask = ReportTask::factory()->create(['narrative' => $oldNarrative]);

        ($this->action)($reportTask, 'New manual narrative.');

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $oldNarrative,
            'source'             => NarrativeSource::ManualEdit->value,
        ]);
    }

    public function test_sets_is_edited_to_true(): void
    {
        $reportTask = ReportTask::factory()->llmGenerated()->create();

        $result = ($this->action)($reportTask, 'Manually written narrative.');

        $this->assertTrue($result->is_edited);
    }

    public function test_updates_narrative_text(): void
    {
        $reportTask = ReportTask::factory()->create();
        $newText = 'Manually written narrative text.';

        $result = ($this->action)($reportTask, $newText);

        $this->assertEquals($newText, $result->narrative);
    }

    public function test_dispatches_narrative_edited_event(): void
    {
        Event::fake([NarrativeEdited::class]);

        $oldNarrative = 'Original text.';
        $reportTask = ReportTask::factory()->create(['narrative' => $oldNarrative]);

        ($this->action)($reportTask, 'New text.');

        Event::assertDispatched(NarrativeEdited::class, function (NarrativeEdited $event) use ($reportTask, $oldNarrative): bool {
            return $event->narratable->is($reportTask) && $event->previousNarrative === $oldNarrative;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new EditTaskNarrative();
    }
}

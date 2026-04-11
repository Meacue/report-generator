<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\EditTaskNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new EditTaskNarrative(new NarrativeSupport());
    }
}

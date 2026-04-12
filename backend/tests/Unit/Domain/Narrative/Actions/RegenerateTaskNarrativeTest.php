<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\RegenerateTaskNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class RegenerateTaskNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private MockLlmProvider $mockLlm;

    private RegenerateTaskNarrative $action;

    public function test_saves_history(): void
    {
        $previousNarrative = 'Previous narrative content.';
        $reportTask = ReportTask::factory()->create(['narrative' => $previousNarrative]);

        ($this->action)($reportTask);

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $previousNarrative,
            'source'             => NarrativeSource::LlmRegeneration->value,
        ]);
    }

    public function test_updates_narrative(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Old narrative.']);

        $result = ($this->action)($reportTask);

        $this->assertEquals($this->mockLlm->narrativeText, $result->narrative);
    }

    public function test_sets_is_edited_to_false(): void
    {
        $reportTask = ReportTask::factory()->edited()->create();

        $result = ($this->action)($reportTask);

        $this->assertFalse($result->is_edited);
    }

    public function test_passes_task_title_to_llm(): void
    {
        $task = Task::factory()->create(['title' => 'Implement login feature']);
        $reportTask = ReportTask::factory()->create([
            'task_id'   => $task->id,
            'narrative' => 'Old narrative.',
        ]);

        ($this->action)($reportTask);

        $this->assertCount(1, $this->mockLlm->narrativeRequests);
        $this->assertEquals('Implement login feature', $this->mockLlm->narrativeRequests[0]->taskTitle);
    }

    public function test_llm_failure_saves_placeholder(): void
    {
        $this->mockLlm->shouldFail = true;
        $reportTask = ReportTask::factory()->create(['narrative' => 'Original narrative.']);

        $result = ($this->action)($reportTask);

        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $result->narrative
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLlm = new MockLlmProvider();
        $this->action = new RegenerateTaskNarrative($this->mockLlm, new NarrativeSupport());
    }
}

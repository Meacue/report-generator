<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\RegenerateDayNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Events\NarrativeRegenerated;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class RegenerateDayNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private MockLlmProvider $mockLlm;

    private RegenerateDayNarrative $action;

    public function test_saves_history(): void
    {
        $previousNarrative = 'Previous day narrative.';
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['narrative' => $previousNarrative]);

        ($this->action)($reportDay);

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => ReportDay::MORPH_ALIAS,
            'narratable_id'      => $reportDay->id,
            'previous_narrative' => $previousNarrative,
            'source'             => NarrativeSource::LlmRegeneration->value,
        ]);
    }

    public function test_updates_narrative(): void
    {
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create();

        $result = ($this->action)($reportDay);

        $this->assertEquals($this->mockLlm->fallbackText, $result->narrative);
    }

    public function test_sets_is_edited_to_false(): void
    {
        $reportDay = ReportDay::factory()->manuallyEdited()->create();

        $result = ($this->action)($reportDay);

        $this->assertFalse($result->is_edited);
    }

    public function test_llm_failure_saves_placeholder(): void
    {
        $this->mockLlm->shouldFail = true;
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create();

        $result = ($this->action)($reportDay);

        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $result->narrative
        );
    }

    public function test_dispatches_narrative_regenerated_event(): void
    {
        Event::fake([NarrativeRegenerated::class]);

        $previousNarrative = 'Old day narrative text.';
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['narrative' => $previousNarrative]);

        ($this->action)($reportDay);

        Event::assertDispatched(NarrativeRegenerated::class, function (NarrativeRegenerated $event) use ($reportDay, $previousNarrative): bool {
            return $event->narratable->is($reportDay) && $event->previousNarrative === $previousNarrative;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLlm = new MockLlmProvider();
        $this->action = new RegenerateDayNarrative($this->mockLlm, new NarrativeSupport());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Actions\UndoDayNarrative;
use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UndoDayNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private UndoDayNarrative $action;

    public function test_restores_previous(): void
    {
        $previousNarrative = 'Previous day narrative content.';
        $reportDay = ReportDay::factory()->fromCommits()->create(['narrative' => 'Current day narrative.']);

        NarrativeHistory::factory()->create([
            'narratable_type'    => ReportDay::MORPH_ALIAS,
            'narratable_id'      => $reportDay->id,
            'previous_narrative' => $previousNarrative,
            'changed_at'         => now(),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        $result = ($this->action)($reportDay);

        $this->assertNotNull($result);
        $this->assertEquals($previousNarrative, $result->narrative);
    }

    public function test_returns_null_when_no_history(): void
    {
        $reportDay = ReportDay::factory()->fromCommits()->create();

        $result = ($this->action)($reportDay);

        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UndoDayNarrative(new NarrativeSupport());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NarrativeSupportTest extends TestCase
{
    use RefreshDatabase;

    private NarrativeSupport $support;

    public function test_history_limited_to_5_entries(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Narrative v0.']);

        for ($i = 1; $i <= 5; $i++) {
            NarrativeHistory::factory()->create([
                'narratable_type'    => ReportTask::MORPH_ALIAS,
                'narratable_id'      => $reportTask->id,
                'previous_narrative' => "Narrative v{$i}.",
                'changed_at'         => now()->subMinutes(6 - $i),
                'source'             => NarrativeSource::ManualEdit,
            ]);
        }

        $this->support->saveHistory($reportTask, NarrativeSource::ManualEdit);

        $count = NarrativeHistory::query()
            ->where('narratable_type', ReportTask::MORPH_ALIAS)
            ->where('narratable_id', $reportTask->id)
            ->count();

        $this->assertEquals(5, $count);
    }

    public function test_history_oldest_entry_deleted_when_pruned(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Narrative v0.']);

        $oldest = NarrativeHistory::factory()->create([
            'narratable_type'    => ReportTask::MORPH_ALIAS,
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => 'Oldest narrative.',
            'changed_at'         => now()->subHour(),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            NarrativeHistory::factory()->create([
                'narratable_type'    => ReportTask::MORPH_ALIAS,
                'narratable_id'      => $reportTask->id,
                'previous_narrative' => "Narrative v{$i}.",
                'changed_at'         => now()->subMinutes(5 - $i),
                'source'             => NarrativeSource::ManualEdit,
            ]);
        }

        $this->support->saveHistory($reportTask, NarrativeSource::ManualEdit);

        $this->assertDatabaseMissing('narrative_history', ['id' => $oldest->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->support = new NarrativeSupport();
    }
}

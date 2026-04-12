<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Events;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Actions\MatchAllUnmatched;
use App\Domain\Matching\Actions\MatchBranch;
use App\Domain\Matching\Listeners\MatchBranchesOnSyncCompleted;
use App\Domain\Sync\Events\SyncCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SyncCompletedTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_calls_match_all_unmatched(): void
    {
        // GIVEN — several unmatched branches exist in the database
        Branch::factory()->create(['parsed_task_number' => '100']);
        Branch::factory()->create(['parsed_task_number' => '200']);
        Branch::factory()->create(['parsed_task_number' => null]);

        $listener = new MatchBranchesOnSyncCompleted(
            new MatchAllUnmatched(new MatchBranch())
        );
        $event = new SyncCompleted();

        // WHEN — the listener handles the SyncCompleted event
        $listener->handle($event);

        // THEN — every branch now has a match result record
        foreach (Branch::all() as $branch) {
            $this->assertTrue(
                $branch->matchResults()->exists(),
                "Branch #{$branch->id} should have been matched after SyncCompleted."
            );
        }
    }
}

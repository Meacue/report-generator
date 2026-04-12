<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Matching\Events\BranchMatched;
use App\Domain\Matching\Listeners\MatchBranchesOnSyncCompleted;
use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Narrative\Events\NarrativeRegenerated;
use App\Domain\Narrative\Listeners\GenerateNarrativesOnReportGenerated;
use App\Domain\Narrative\Listeners\SaveNarrativeHistory;
use App\Domain\Report\Events\ReportGenerated;
use App\Domain\Sync\Events\SyncCompleted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        ReportGenerated::class => [
            GenerateNarrativesOnReportGenerated::class,
        ],
        SyncCompleted::class => [
            MatchBranchesOnSyncCompleted::class,
        ],
        BranchMatched::class   => [],
        NarrativeEdited::class => [
            SaveNarrativeHistory::class,
        ],
        NarrativeRegenerated::class => [
            SaveNarrativeHistory::class,
        ],
    ];
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncJob;
use Illuminate\Console\Command;

final class SyncResetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:reset {--force : Skip confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'Mark all in-progress SyncJob rows as failed';

    public function handle(): int
    {
        if (! (bool) $this->option('force') && ! $this->confirm('Reset all in-progress SyncJob to failed?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $jobs = SyncJob::query()->where('status', SyncStatus::InProgress)->get();

        $jobs->each(fn (SyncJob $job) => $job->markFailed('Reset via sync:reset'));

        $this->info("Marked {$jobs->count()} SyncJob(s) as failed.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Matching\Actions\MatchAllUnmatched;
use App\Domain\Sync\Actions\SyncBitrix24;
use App\Domain\Sync\Actions\SyncGitLab;
use App\Domain\Sync\Enums\SyncStatus;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:run
        {--gitlab-only : Sync only GitLab data}
        {--bitrix24-only : Sync only Bitrix24 data}';

    /**
     * @var string
     */
    protected $description = 'Run data synchronization from GitLab and Bitrix24';

    public function handle(SyncGitLab $syncGitLab, SyncBitrix24 $syncBitrix24, MatchAllUnmatched $matchAllUnmatched): int
    {
        $gitlabOnly = (bool) $this->option('gitlab-only');
        $bitrix24Only = (bool) $this->option('bitrix24-only');

        if ($gitlabOnly && $bitrix24Only) {
            $this->error('Cannot use --gitlab-only and --bitrix24-only together.');

            return self::FAILURE;
        }

        if ($gitlabOnly) {
            $this->info('Syncing GitLab data...');
            $log = $syncGitLab();

            /** @var SyncStatus $logStatus */
            $logStatus = $log->status;

            if ($logStatus === SyncStatus::Failed) {
                $this->error("GitLab sync failed: {$log->error_message}");

                return self::FAILURE;
            }

            $this->info("GitLab sync completed. Items synced: {$log->items_synced}");

            return self::SUCCESS;
        }

        if ($bitrix24Only) {
            $this->info('Syncing Bitrix24 data...');
            $log = $syncBitrix24();

            /** @var SyncStatus $logStatus */
            $logStatus = $log->status;

            if ($logStatus === SyncStatus::Failed) {
                $this->error("Bitrix24 sync failed: {$log->error_message}");

                return self::FAILURE;
            }

            $this->info("Bitrix24 sync completed. Items synced: {$log->items_synced}");

            return self::SUCCESS;
        }

        $this->info('Running full synchronization...');
        $syncGitLab();
        $syncBitrix24();
        $matchAllUnmatched();
        $this->info('Full synchronization completed.');

        return self::SUCCESS;
    }
}

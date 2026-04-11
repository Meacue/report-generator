<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Enums\SyncStep;
use App\Domain\Sync\Models\SyncJob;
use App\Domain\Matching\Actions\MatchAllUnmatched;
use App\Services\Sync\SyncServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class RunSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $syncJobId,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
    ) {
    }

    public function handle(SyncServiceInterface $syncService, MatchAllUnmatched $matchAllUnmatched): void
    {
        $syncJob = SyncJob::findOrFail($this->syncJobId);

        try {
            if ($this->dateFrom !== null && $this->dateTo !== null) {
                $this->runResync($syncJob, $syncService, $matchAllUnmatched);
            } else {
                $this->runFullSync($syncJob, $syncService, $matchAllUnmatched);
            }
        } catch (\Throwable $e) {
            $syncJob->markFailed($e->getMessage());
            $this->publishProgress(['status' => 'failed', 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $syncJob = SyncJob::find($this->syncJobId);

        if ($syncJob instanceof SyncJob && $syncJob->status === SyncStatus::InProgress) {
            $syncJob->markFailed($exception->getMessage());
            $this->publishProgress(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }

    private function runFullSync(
        SyncJob $syncJob,
        SyncServiceInterface $syncService,
        MatchAllUnmatched $matchAllUnmatched,
    ): void {
        $syncJob->markStep(SyncStep::GitLab);
        $this->publishProgress(['status' => 'in_progress', 'current_step' => 'gitlab']);
        $syncService->syncGitLab();

        $syncJob->markStep(SyncStep::Bitrix24);
        $this->publishProgress(['status' => 'in_progress', 'current_step' => 'bitrix24']);
        $syncService->syncBitrix24();

        $syncJob->markStep(SyncStep::Matching);
        $this->publishProgress(['status' => 'in_progress', 'current_step' => 'matching']);
        $matchAllUnmatched();

        $syncJob->markCompleted();
        $this->publishProgress(['status' => 'success']);
    }

    private function runResync(
        SyncJob $syncJob,
        SyncServiceInterface $syncService,
        MatchAllUnmatched $matchAllUnmatched,
    ): void {
        /** @var string $dateFrom */
        $dateFrom = $this->dateFrom;
        /** @var string $dateTo */
        $dateTo = $this->dateTo;

        $syncJob->markStep(SyncStep::GitLab);
        $this->publishProgress(['status' => 'in_progress', 'current_step' => 'gitlab']);
        $syncService->syncGitLab();

        $syncJob->markStep(SyncStep::Bitrix24);
        $this->publishProgress(['status' => 'in_progress', 'current_step' => 'bitrix24']);
        $syncService->syncBitrix24();

        $syncJob->markStep(SyncStep::Matching);
        $this->publishProgress(['status' => 'in_progress', 'current_step' => 'matching']);
        $matchAllUnmatched();

        $syncJob->markCompleted();
        $this->publishProgress(['status' => 'success']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function publishProgress(array $data): void
    {
        Redis::publish('sync:progress', (string) json_encode($data));
    }
}

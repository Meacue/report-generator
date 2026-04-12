<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\Actions\SyncBitrix24;
use App\Domain\Sync\Actions\SyncGitLab;
use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Events\SyncCompleted;
use App\Domain\Sync\Enums\SyncStep;
use App\Domain\Sync\Models\SyncJob;
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

    public function handle(SyncGitLab $syncGitLab, SyncBitrix24 $syncBitrix24): void
    {
        $syncJob = SyncJob::findOrFail($this->syncJobId);

        try {
            $syncJob->markStep(SyncStep::GitLab);
            $this->publishProgress(['status' => 'in_progress', 'current_step' => 'gitlab']);
            $syncGitLab($this->dateFrom, $this->dateTo);

            $syncJob->markStep(SyncStep::Bitrix24);
            $this->publishProgress(['status' => 'in_progress', 'current_step' => 'bitrix24']);
            $syncBitrix24();

            $syncJob->markStep(SyncStep::Matching);
            $this->publishProgress(['status' => 'in_progress', 'current_step' => 'matching']);
            SyncCompleted::dispatch();

            $syncJob->markCompleted();
            $this->publishProgress(['status' => 'success']);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function publishProgress(array $data): void
    {
        Redis::publish('sync:progress', (string) json_encode($data));
    }
}

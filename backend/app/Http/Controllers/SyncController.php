<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Enums\SyncStatus;
use App\Jobs\RunSyncJob;
use App\Domain\Sync\Models\SyncJob;
use App\Domain\Sync\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;
use RedisException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SyncController extends Controller
{
    public function status(): JsonResponse
    {
        /** @var SyncJob|null $runningSyncJob */
        $runningSyncJob = SyncJob::query()
            ->where('status', SyncStatus::InProgress)
            ->latest('started_at')
            ->first();

        if ($runningSyncJob !== null) {
            if ($this->failIfStale($runningSyncJob)) {
                return $this->buildStatusFromSyncLog();
            }

            return response()->json([
                'status'        => 'in_progress',
                'current_step'  => $runningSyncJob->current_step?->value,
                'error_message' => null,
                'last_sync_at'  => null,
                'source'        => null,
                'items_synced'  => 0,
            ]);
        }

        return $this->buildStatusFromSyncLog();
    }

    public function trigger(): JsonResponse
    {
        if (SyncJob::isRunning()) {
            return response()->json(['error' => 'Sync is already in progress'], 409);
        }

        $missing = $this->missingCredentials();
        if ($missing !== []) {
            return response()->json([
                'error'   => 'Credentials are not configured',
                'missing' => $missing,
            ], 422);
        }

        $syncJob = SyncJob::create([
            'type'       => 'full',
            'status'     => SyncStatus::InProgress,
            'started_at' => now(),
        ]);

        RunSyncJob::dispatch($syncJob->id);

        return response()->json([
            'message'     => 'Sync started',
            'sync_job_id' => $syncJob->id,
        ], 202);
    }

    public function resync(Request $request): JsonResponse
    {
        if (SyncJob::isRunning()) {
            return response()->json(['error' => 'Sync is already in progress'], 409);
        }

        $missing = $this->missingCredentials();
        if ($missing !== []) {
            return response()->json([
                'error'   => 'Credentials are not configured',
                'missing' => $missing,
            ], 422);
        }

        /** @var array{date_from: string, date_to: string} $validated */
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $dateRange = new DateRange($validated['date_from'], $validated['date_to']);

        $syncJob = SyncJob::create([
            'type'       => 'resync',
            'status'     => SyncStatus::InProgress,
            'params'     => $dateRange->toArray(),
            'started_at' => now(),
        ]);

        RunSyncJob::dispatch(
            $syncJob->id,
            $dateRange->from->toDateString(),
            $dateRange->to->toDateString(),
        );

        return response()->json([
            'message'     => 'Resync started',
            'sync_job_id' => $syncJob->id,
        ], 202);
    }

    public function stream(): StreamedResponse
    {
        return new StreamedResponse(function (): void {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            /** @var SyncJob|null $currentJob */
            $currentJob = SyncJob::query()->latest('started_at')->first();

            $this->sendInitialState($currentJob);

            if ($currentJob === null) {
                return;
            }

            if (in_array($currentJob->status, [SyncStatus::Success, SyncStatus::Failed], true)) {
                $this->sendSSE('done', [
                    'status'        => $currentJob->status->value,
                    'error_message' => $currentJob->error_message,
                ]);

                return;
            }

            $this->listenForProgressOnDedicatedConnection($currentJob->id);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Subscribe to sync progress via a dedicated Redis connection.
     *
     * Uses a separate connection because disconnect() is the only way
     * to exit phpredis' blocking subscribe() — and it throws RedisException.
     */
    private function listenForProgressOnDedicatedConnection(int $syncJobId): void
    {
        /** @var PhpRedisConnection $redis */
        $redis = Redis::connection('subscribe');

        while (! connection_aborted()) {
            $subscribedNormally = false;
            try {
                $redis->subscribe(['sync:progress'], function (string $message) use ($redis, &$subscribedNormally): void {
                    $subscribedNormally = true;
                    /** @var array<string, mixed> $data */
                    $data = json_decode($message, true);
                    $this->sendSSE('progress', $data);

                    $status = $data['status'] ?? null;
                    if ($status === 'success' || $status === 'failed') {
                        $this->sendSSE('done', [
                            'status'        => $status,
                            'error_message' => $data['error_message'] ?? null,
                        ]);
                        $redis->disconnect();
                    }
                });

                if ($subscribedNormally) {
                    break;
                }
            } catch (RedisException) {
                // read_timeout — fall through to DB re-check.
            }

            /** @var SyncJob|null $fresh */
            $fresh = SyncJob::find($syncJobId);
            if ($fresh !== null && in_array($fresh->status, [SyncStatus::Success, SyncStatus::Failed], true)) {
                $this->sendSSE('done', [
                    'status'        => $fresh->status->value,
                    'error_message' => $fresh->error_message,
                ]);
                break;
            }

            $this->sendHeartbeat();
            if (connection_aborted()) {
                break;
            }

            // After a read timeout, phpredis leaves the subscribe connection in a state
            // where the next subscribe() call returns immediately with no events. Purge the
            // cached singleton so Redis::connection() produces a fresh phpredis client.
            Redis::purge('subscribe');
            /** @var PhpRedisConnection $redis */
            $redis = Redis::connection('subscribe');
        }
    }

    private function failIfStale(SyncJob $syncJob): bool
    {
        if (! $syncJob->isStale()) {
            return false;
        }

        $syncJob->markFailed('Sync timed out');

        return true;
    }

    /** @return list<string> */
    private function missingCredentials(): array
    {
        $settings = Setting::query()->first();
        $missing = [];

        if (! $this->hasEncrypted($settings, 'gitlab_token')) {
            $missing[] = 'gitlab_token';
        }
        if ($settings === null || $settings->bitrix24_rest_url === null || $settings->bitrix24_rest_url === '') {
            $missing[] = 'bitrix24_rest_url';
        }
        if ($settings === null || $settings->bitrix24_user_id === null || $settings->bitrix24_user_id === '') {
            $missing[] = 'bitrix24_user_id';
        }
        if (! $this->hasEncrypted($settings, 'bitrix24_api_key')) {
            $missing[] = 'bitrix24_api_key';
        }

        return $missing;
    }

    private function hasEncrypted(?Setting $settings, string $column): bool
    {
        if ($settings === null) {
            return false;
        }
        $raw = $settings->getRawOriginal($column);

        return is_string($raw) && $raw !== '';
    }

    private function sendInitialState(?SyncJob $job): void
    {
        if ($job === null) {
            $this->sendSSE('state', ['status' => 'never']);

            return;
        }

        $this->sendSSE('state', [
            'status'        => $job->status->value,
            'current_step'  => $job->current_step?->value,
            'error_message' => $job->error_message,
        ]);
    }

    private function sendHeartbeat(): void
    {
        echo ": ping\n\n";

        if (connection_aborted()) {
            return;
        }

        flush();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendSSE(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";

        if (connection_aborted()) {
            return;
        }

        flush();
    }

    private function buildStatusFromSyncLog(): JsonResponse
    {
        /** @var SyncLog|null $lastSync */
        $lastSync = SyncLog::query()->latest('started_at')->first();

        $failedResponse = $this->buildFailedSyncJobResponse($lastSync);
        if ($failedResponse !== null) {
            return $failedResponse;
        }

        if ($lastSync === null) {
            return response()->json([
                'status'        => 'never',
                'current_step'  => null,
                'error_message' => null,
                'last_sync_at'  => null,
                'source'        => null,
                'items_synced'  => 0,
            ]);
        }

        return response()->json([
            'status'        => $lastSync->status->value,
            'current_step'  => null,
            'error_message' => $lastSync->error_message,
            'last_sync_at'  => $lastSync->completed_at?->toISOString(),
            'source'        => $lastSync->source->value,
            'items_synced'  => $lastSync->items_synced,
        ]);
    }

    /**
     * Build a failed-status response if the latest SyncJob has failed.
     */
    private function buildFailedSyncJobResponse(?SyncLog $lastSync): ?JsonResponse
    {
        /** @var SyncJob|null $lastSyncJob */
        $lastSyncJob = SyncJob::query()->latest('started_at')->first();

        if ($lastSyncJob === null || $lastSyncJob->status !== SyncStatus::Failed) {
            return null;
        }

        return response()->json([
            'status'        => 'failed',
            'current_step'  => null,
            'error_message' => $lastSyncJob->error_message,
            'last_sync_at'  => $lastSync?->completed_at?->toISOString(),
            'source'        => $lastSync !== null ? $lastSync->source->value : null,
            'items_synced'  => $lastSync !== null ? $lastSync->items_synced : 0,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\SyncStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property SyncStatus $status
 * @property SyncStep|null $current_step
 * @property array<string, mixed>|null $params
 * @property string|null $error_message
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SyncJob extends Model
{
    protected $fillable = [
        'type',
        'status',
        'current_step',
        'params',
        'error_message',
        'started_at',
        'completed_at',
    ];

    public function markStep(SyncStep $step): void
    {
        $this->update(['current_step' => $step]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => SyncStatus::Success,
            'current_step' => null,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => SyncStatus::Failed,
            'error_message' => $error,
            'completed_at'  => now(),
        ]);
    }

    public static function isRunning(): bool
    {
        return self::query()
            ->where('status', SyncStatus::InProgress)
            ->exists();
    }

    public function isStale(int $timeoutMinutes = 10): bool
    {
        return $this->status === SyncStatus::InProgress
            && $this->updated_at !== null
            && $this->updated_at->diffInMinutes(now()) > $timeoutMinutes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status'       => SyncStatus::class,
            'current_step' => SyncStep::class,
            'params'       => 'array',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}

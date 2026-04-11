<?php

declare(strict_types=1);

namespace App\Domain\Sync\Models;

use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use Database\Factories\SyncLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SyncSource $source
 * @property SyncStatus $status
 * @property int $items_synced
 * @property string|null $error_message
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class SyncLog extends Model
{
    /** @use HasFactory<SyncLogFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'status',
        'items_synced',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected static function newFactory(): SyncLogFactory
    {
        return SyncLogFactory::new();
    }

    protected function casts(): array
    {
        return [
            'source'       => SyncSource::class,
            'status'       => SyncStatus::class,
            'items_synced' => 'integer',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}

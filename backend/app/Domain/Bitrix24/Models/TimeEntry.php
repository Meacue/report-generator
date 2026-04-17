<?php

declare(strict_types=1);

namespace App\Domain\Bitrix24\Models;

use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bitrix24_entry_id
 * @property int $bitrix24_task_id
 * @property string $bitrix24_user_id
 * @property int $seconds
 * @property string|null $comment
 * @property Carbon $tracked_at
 * @property Carbon|null $source_created_at
 * @property Carbon $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected $table = 'task_time_entries';

    protected $fillable = [
        'bitrix24_entry_id',
        'bitrix24_task_id',
        'bitrix24_user_id',
        'seconds',
        'comment',
        'tracked_at',
        'source_created_at',
        'synced_at',
    ];

    /**
     * Relation to Task via the business key bitrix24_task_id.
     * Returns null when the parent task has not yet been synced.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'bitrix24_task_id', 'bitrix24_task_id');
    }

    protected static function newFactory(): TimeEntryFactory
    {
        return TimeEntryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bitrix24_entry_id' => 'integer',
            'bitrix24_task_id'  => 'integer',
            'bitrix24_user_id'  => 'string',
            'seconds'           => 'integer',
            'tracked_at'        => 'datetime',
            'source_created_at' => 'datetime',
            'synced_at'         => 'datetime',
        ];
    }
}

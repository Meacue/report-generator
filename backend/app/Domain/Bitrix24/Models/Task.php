<?php

declare(strict_types=1);

namespace App\Domain\Bitrix24\Models;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Matching\Models\MatchResult;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $bitrix24_task_id
 * @property string|null $title
 * @property TaskStatus $status
 * @property int|null $project_id
 * @property string|null $project_name
 * @property list<string>|null $participation_roles
 * @property bool $is_external
 * @property string|null $bitrix24_url
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TimeEntry> $timeEntries
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'bitrix24_task_id',
        'title',
        'status',
        'project_id',
        'project_name',
        'participation_roles',
        'is_external',
        'bitrix24_url',
        'status_changed_at',
        'synced_at',
    ];

    /**
     * @return HasMany<MatchResult, $this>
     */
    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }

    /**
     * Time-tracking entries logged against this task in Bitrix24.
     *
     * The relation uses the business key bitrix24_task_id so it works even
     * when the local PK differs between environments.
     *
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'bitrix24_task_id', 'bitrix24_task_id');
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    protected function casts(): array
    {
        return [
            'bitrix24_task_id'    => 'integer',
            'project_id'          => 'integer',
            'status'              => TaskStatus::class,
            'participation_roles' => 'array',
            'is_external'         => 'boolean',
            'status_changed_at'   => 'datetime',
            'synced_at'           => 'datetime',
        ];
    }
}

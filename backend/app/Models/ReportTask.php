<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $report_id
 * @property int|null $task_id
 * @property string|null $narrative
 * @property string|null $project_name
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Task|null $task
 */
class ReportTask extends Model
{
    /** @use HasFactory<ReportTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'report_id',
        'task_id',
        'narrative',
        'project_name',
        'is_edited',
    ];

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return HasMany<ReportDayTask, $this>
     */
    public function reportDayTasks(): HasMany
    {
        return $this->hasMany(ReportDayTask::class);
    }

    /**
     * @return MorphMany<NarrativeHistory, $this>
     */
    public function narrativeHistory(): MorphMany
    {
        return $this->morphMany(NarrativeHistory::class, 'narratable');
    }

    protected function casts(): array
    {
        return [
            'report_id' => 'integer',
            'task_id'   => 'integer',
            'is_edited' => 'boolean',
        ];
    }
}

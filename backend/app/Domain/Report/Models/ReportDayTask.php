<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Domain\Narrative\Models\NarrativeHistory;
use Database\Factories\ReportDayTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $report_day_id
 * @property int $report_task_id
 * @property string|null $narrative
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReportDayTask extends Model
{
    /** @use HasFactory<ReportDayTaskFactory> */
    use HasFactory;
    public const string MORPH_ALIAS = 'report_day_task';

    protected $fillable = [
        'report_day_id',
        'report_task_id',
        'narrative',
        'is_edited',
    ];

    /**
     * @return BelongsTo<ReportDay, $this>
     */
    public function reportDay(): BelongsTo
    {
        return $this->belongsTo(ReportDay::class);
    }

    /**
     * @return BelongsTo<ReportTask, $this>
     */
    public function reportTask(): BelongsTo
    {
        return $this->belongsTo(ReportTask::class);
    }

    /**
     * @return MorphMany<NarrativeHistory, $this>
     */
    public function narrativeHistory(): MorphMany
    {
        return $this->morphMany(NarrativeHistory::class, 'narratable');
    }

    public function editNarrative(string $newNarrative): void
    {
        $this->update([
            'narrative' => $newNarrative,
            'is_edited' => true,
        ]);
    }

    public function hasNarrative(): bool
    {
        return ! empty($this->narrative);
    }

    public function wasEdited(): bool
    {
        return (bool) $this->is_edited;
    }

    protected static function newFactory(): ReportDayTaskFactory
    {
        return ReportDayTaskFactory::new();
    }

    protected function casts(): array
    {
        return [
            'report_day_id'  => 'integer',
            'report_task_id' => 'integer',
            'is_edited'      => 'boolean',
        ];
    }
}

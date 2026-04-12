<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Report\Enums\ReportDaySource;
use Database\Factories\ReportDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $report_id
 * @property Carbon $date
 * @property string|null $narrative
 * @property ReportDaySource $source
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReportDay extends Model
{
    /** @use HasFactory<ReportDayFactory> */
    use HasFactory;
    public const string MORPH_ALIAS = 'report_day';

    protected $fillable = [
        'report_id',
        'date',
        'narrative',
        'source',
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
     * @return HasMany<ReportDayTask, $this>
     */
    public function reportDayTasks(): HasMany
    {
        return $this->hasMany(ReportDayTask::class);
    }

    public function linkTask(ReportTask $reportTask): ReportDayTask
    {
        return $this->reportDayTasks()->create([
            'report_task_id' => $reportTask->id,
        ]);
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

    protected static function newFactory(): ReportDayFactory
    {
        return ReportDayFactory::new();
    }

    protected function casts(): array
    {
        return [
            'report_id' => 'integer',
            'date'      => 'date',
            'source'    => ReportDaySource::class,
            'is_edited' => 'boolean',
        ];
    }
}

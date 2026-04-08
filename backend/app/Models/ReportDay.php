<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportDaySource;
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
            'date'      => 'date',
            'source'    => ReportDaySource::class,
            'is_edited' => 'boolean',
        ];
    }
}

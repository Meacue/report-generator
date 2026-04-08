<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ReportType $type
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property ReportStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, ReportDay> $reportDays
 * @property Collection<int, ReportTask> $reportTasks
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'date_from',
        'date_to',
        'status',
    ];

    /**
     * @return HasMany<ReportDay, $this>
     */
    public function reportDays(): HasMany
    {
        return $this->hasMany(ReportDay::class);
    }

    /**
     * @return HasMany<ReportTask, $this>
     */
    public function reportTasks(): HasMany
    {
        return $this->hasMany(ReportTask::class);
    }

    protected function casts(): array
    {
        return [
            'type'      => ReportType::class,
            'date_from' => 'date',
            'date_to'   => 'date',
            'status'    => ReportStatus::class,
        ];
    }
}

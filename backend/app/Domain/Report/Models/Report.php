<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Enums\ReportType;
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

    public function markAsGenerated(): void
    {
        $this->update(['status' => ReportStatus::Generated]);
    }

    public function markAsExported(): void
    {
        $this->update(['status' => ReportStatus::Exported]);
    }

    public function isEditable(): bool
    {
        return $this->status !== ReportStatus::Exported;
    }

    public function canBeRegenerated(): bool
    {
        return in_array($this->status, [ReportStatus::Draft, ReportStatus::Generated]);
    }

    public function getDateRange(): DateRange
    {
        return new DateRange($this->date_from->toDateString(), $this->date_to->toDateString());
    }

    public function addDay(string $date, ReportDaySource $source, ?string $narrative = null): ReportDay
    {
        return $this->reportDays()->create([
            'date'      => $date,
            'source'    => $source,
            'narrative' => $narrative,
            'is_edited' => false,
        ]);
    }

    public function addTask(int $taskId, ?string $projectName = null): ReportTask
    {
        return $this->reportTasks()->create([
            'task_id'      => $taskId,
            'narrative'    => null,
            'project_name' => $projectName,
            'is_edited'    => false,
        ]);
    }

    public function findDay(string $date): ?ReportDay
    {
        return $this->reportDays()->whereDate('date', $date)->first();
    }

    public function findTask(int $taskId): ?ReportTask
    {
        return $this->reportTasks()->where('task_id', $taskId)->first();
    }

    public function guardExportable(): void
    {
        if ($this->status === ReportStatus::Draft) {
            throw new \DomainException('Cannot export a draft report. Generate narratives first.');
        }
    }

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
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

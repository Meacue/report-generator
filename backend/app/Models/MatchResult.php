<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use Database\Factories\MatchResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $task_id
 * @property ConfidenceLevel $confidence_level
 * @property ResolvedBy $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class MatchResult extends Model
{
    /** @use HasFactory<MatchResultFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'task_id',
        'confidence_level',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isConfirmedByUser(): bool
    {
        return $this->resolved_by === ResolvedBy::User;
    }

    public function isAutoMatched(): bool
    {
        return $this->confidence_level === ConfidenceLevel::Auto
            && $this->resolved_by === ResolvedBy::System;
    }

    public function isIgnored(): bool
    {
        return $this->task_id === null
            && $this->confidence_level === ConfidenceLevel::None;
    }

    public static function createManualMatch(int $branchId, int $taskId): self
    {
        return self::create([
            'branch_id'        => $branchId,
            'task_id'          => $taskId,
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::User,
            'resolved_at'      => now(),
        ]);
    }

    public static function createIgnored(int $branchId): self
    {
        return self::create([
            'branch_id'        => $branchId,
            'task_id'          => null,
            'confidence_level' => ConfidenceLevel::None,
            'resolved_by'      => ResolvedBy::User,
            'resolved_at'      => now(),
        ]);
    }

    protected function casts(): array
    {
        return [
            'branch_id'        => 'integer',
            'task_id'          => 'integer',
            'confidence_level' => ConfidenceLevel::class,
            'resolved_by'      => ResolvedBy::class,
            'resolved_at'      => 'datetime',
        ];
    }
}

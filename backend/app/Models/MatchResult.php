<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\ResolvedBy;
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

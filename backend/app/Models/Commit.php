<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $gitlab_commit_sha
 * @property string $message
 * @property string|null $conventional_type
 * @property string $author
 * @property Carbon $committed_at
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Commit extends Model
{
    /** @use HasFactory<CommitFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'gitlab_commit_sha',
        'message',
        'conventional_type',
        'author',
        'committed_at',
        'synced_at',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'branch_id'    => 'integer',
            'committed_at' => 'datetime',
            'synced_at'    => 'datetime',
        ];
    }
}

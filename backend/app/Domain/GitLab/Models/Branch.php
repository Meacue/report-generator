<?php

declare(strict_types=1);

namespace App\Domain\GitLab\Models;

use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Shared\ValueObjects\DateRange;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gitlab_repo_id
 * @property string $branch_name
 * @property int|null $parsed_task_number
 * @property Carbon|null $parsed_date
 * @property string|null $parsed_parent_branch
 * @property string|null $parsed_info
 * @property int|null $gitlab_mr_iid
 * @property string|null $mr_state
 * @property string|null $mr_target_branch
 * @property string|null $mr_web_url
 * @property string|null $mr_title
 * @property string|null $mr_description
 * @property int|null $mr_additions
 * @property int|null $mr_deletions
 * @property array<int, string>|null $mr_changed_files
 * @property Carbon|null $mr_merged_at
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Commit> $commits
 * @property-read Collection<int, MatchResult> $matchResults
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'gitlab_repo_id',
        'branch_name',
        'parsed_task_number',
        'parsed_date',
        'parsed_parent_branch',
        'parsed_info',
        'gitlab_mr_iid',
        'mr_state',
        'mr_target_branch',
        'mr_web_url',
        'mr_title',
        'mr_description',
        'mr_additions',
        'mr_deletions',
        'mr_changed_files',
        'mr_merged_at',
        'synced_at',
    ];

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }

    /**
     * @return HasMany<MatchResult, $this>
     */
    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }

    public function isMatched(): bool
    {
        return $this->matchResults()
            ->whereNotNull('task_id')
            ->where('resolved_by', ResolvedBy::User)
            ->exists();
    }

    public function hasTaskNumber(): bool
    {
        return $this->parsed_task_number !== null;
    }

    /**
     * @return Collection<int, Commit>
     */
    public function getCommitsInPeriod(DateRange $dateRange): Collection
    {
        return $this->commits()
            ->whereBetween('committed_at', [$dateRange->from, $dateRange->to])
            ->get();
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    protected function casts(): array
    {
        return [
            'gitlab_repo_id'     => 'integer',
            'parsed_task_number' => 'integer',
            'parsed_date'        => 'date',
            'gitlab_mr_iid'      => 'integer',
            'mr_additions'       => 'integer',
            'mr_deletions'       => 'integer',
            'mr_changed_files'   => 'array',
            'mr_merged_at'       => 'datetime',
            'synced_at'          => 'datetime',
        ];
    }
}

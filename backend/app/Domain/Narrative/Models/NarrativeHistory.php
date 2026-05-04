<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Models;

use App\Domain\Narrative\Enums\NarrativeSource;
use Carbon\CarbonImmutable;
use Database\Factories\NarrativeHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $narratable_type
 * @property int $narratable_id
 * @property string|null $previous_narrative
 * @property CarbonImmutable $changed_at
 * @property NarrativeSource $source
 */
class NarrativeHistory extends Model
{
    /** @use HasFactory<NarrativeHistoryFactory> */
    use HasFactory;

    protected $table = 'narrative_history';

    protected $fillable = [
        'narratable_type',
        'narratable_id',
        'previous_narrative',
        'changed_at',
        'source',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function narratable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): NarrativeHistoryFactory
    {
        return NarrativeHistoryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'source'     => NarrativeSource::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NarrativeSource;
use Database\Factories\NarrativeHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'source'     => NarrativeSource::class,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VectorEmbedding extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'content_text',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeBySource(Builder $query, string $sourceType, ?int $sourceId = null): Builder
    {
        $query->where('source_type', $sourceType);

        if ($sourceId !== null) {
            $query->where('source_id', $sourceId);
        }

        return $query;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI学習基盤: 実施プログラム分類の付与(記録→カテゴリ。多相)。
 * method 優先度 manual > embedding > rule。
 */
class ProgramClassification extends Model
{
    protected $fillable = [
        'classifiable_type', 'classifiable_id', 'program_category_id',
        'method', 'confidence', 'is_primary', 'classified_by',
    ];

    protected $casts = [
        'confidence' => 'float',
        'is_primary' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProgramCategory::class, 'program_category_id');
    }
}

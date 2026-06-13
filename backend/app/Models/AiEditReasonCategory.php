<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI学習基盤: 修正理由カテゴリ(固定11 + 動的追加のチップ)。
 */
class AiEditReasonCategory extends Model
{
    protected $fillable = [
        'code', 'label_ja', 'description', 'company_id', 'is_seeded', 'status',
        'sort_order', 'usage_count', 'centroid_meta', 'promoted_from_candidate_id',
    ];

    protected $casts = [
        'is_seeded' => 'boolean',
        'sort_order' => 'integer',
        'usage_count' => 'integer',
        'centroid_meta' => 'array',
    ];
}

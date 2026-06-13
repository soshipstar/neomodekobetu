<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI学習基盤: 修正理由の新カテゴリ候補(自由記述の束ね。昇格待ち)。
 */
class AiEditReasonCandidate extends Model
{
    protected $fillable = [
        'company_id', 'normalized_text', 'member_texts', 'frequency', 'distinct_users',
        'nearest_category_sim', 'status', 'merged_into_category_id', 'detection_meta',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'member_texts' => 'array',
        'detection_meta' => 'array',
        'frequency' => 'integer',
        'distinct_users' => 'integer',
        'nearest_category_sim' => 'float',
        'reviewed_at' => 'datetime',
    ];
}

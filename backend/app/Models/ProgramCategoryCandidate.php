<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI学習基盤: 実施プログラム分類の新カテゴリ候補(自由入力→昇格待ち。動的タクソノミー)。
 */
class ProgramCategoryCandidate extends Model
{
    protected $fillable = [
        'company_id', 'normalized_text', 'frequency', 'distinct_users',
        'nearest_category_sim', 'status', 'merged_into_category_id',
        'detection_meta', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'frequency' => 'integer',
        'distinct_users' => 'integer',
        'nearest_category_sim' => 'float',
        'detection_meta' => 'array',
        'reviewed_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI学習基盤: 実施プログラム分類カテゴリ(5領域 × プログラム種別。固定seed + 法人別/動的)。
 */
class ProgramCategory extends Model
{
    protected $fillable = [
        'domain', 'code', 'label_ja', 'parent_id', 'aliases', 'description',
        'company_id', 'is_seeded', 'status', 'sort_order', 'usage_count',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_seeded' => 'boolean',
        'sort_order' => 'integer',
        'usage_count' => 'integer',
    ];
}

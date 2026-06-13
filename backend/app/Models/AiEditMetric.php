<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI学習基盤 Layer2: 期間×次元の修正傾向ロールアップ(AiEditMetricsService が再計算)。
 */
class AiEditMetric extends Model
{
    public $timestamps = false; // computed_at のみ

    protected $fillable = [
        'period_ym', 'facet', 'company_id', 'classroom_id', 'subj_cohort', 'subj_growth_stage',
        'author_user_id', 'document_type', 'support_category', 'program_category_id',
        'gen_count', 'revision_count', 'edited_document_count', 'distinct_students',
        'edit_rate', 'change_ratio_avg', 'change_ratio_p50', 'change_ratio_p90',
        'ai_acceptance', 'top_reason_categories', 'computed_at',
    ];

    protected $casts = [
        'gen_count' => 'integer',
        'revision_count' => 'integer',
        'edited_document_count' => 'integer',
        'distinct_students' => 'integer',
        'edit_rate' => 'float',
        'change_ratio_avg' => 'float',
        'change_ratio_p50' => 'float',
        'change_ratio_p90' => 'float',
        'ai_acceptance' => 'float',
        'top_reason_categories' => 'array',
        'computed_at' => 'datetime',
    ];
}

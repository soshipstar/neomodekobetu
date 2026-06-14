<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 支援知蒸留 D4: 法人内で蒸留した支援知(L3)。条件(コホート×成長段階)別。
 */
class SupportKnowledge extends Model
{
    public $timestamps = false; // computed_at のみ

    protected $table = 'support_knowledge';

    protected $fillable = [
        'company_id', 'cohort', 'growth_stage', 'sample_n',
        'top_support_categories', 'top_programs',
        'outcome_objective_delta_avg', 'outcome_monitoring_pct_avg', 'outcome_agreement_avg',
        'exemplar_excerpts', 'exemplar_count', 'computed_at',
    ];

    protected $casts = [
        'sample_n' => 'integer',
        'top_support_categories' => 'array',
        'top_programs' => 'array',
        'outcome_objective_delta_avg' => 'float',
        'outcome_monitoring_pct_avg' => 'float',
        'outcome_agreement_avg' => 'float',
        'exemplar_excerpts' => 'array',
        'exemplar_count' => 'integer',
        'computed_at' => 'datetime',
    ];
}

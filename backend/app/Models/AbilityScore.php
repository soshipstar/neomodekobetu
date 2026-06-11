<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価: 評価スコア(T_評価スコア)。個人内評価の時系列履歴(上書きせず追記)。
 */
class AbilityScore extends Model
{
    protected $table = 'ability_scores';

    protected $fillable = [
        'student_id', 'item_id', 'axis_id', 'score', 'prev_score', 'change',
        'needs_review', 'method', 'evaluated_by', 'evidence_record_ids', 'notes', 'evaluated_on',
    ];

    protected $casts = [
        'score' => 'integer',
        'prev_score' => 'integer',
        'change' => 'integer',
        'needs_review' => 'boolean',
        'evidence_record_ids' => 'array',
        'evaluated_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalItem::class, 'item_id', 'item_id');
    }
}

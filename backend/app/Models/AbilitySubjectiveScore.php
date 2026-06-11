<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価: mynameis から受信した主観自己評価(項目ごとの最新値, 1〜5 Likert)。
 */
class AbilitySubjectiveScore extends Model
{
    protected $table = 'ability_subjective_scores';

    protected $fillable = [
        'student_id', 'item_id', 'axis_id', 'response_value', 'responded_at', 'source',
    ];

    protected $casts = [
        'response_value' => 'integer',
        'responded_at' => 'datetime',
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

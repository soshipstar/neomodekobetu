<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価マスタ: 才能判定基準(才能サイン×水準1〜4)。
 */
class AbilityTalentCriterion extends Model
{
    protected $table = 'ability_talent_criteria';

    protected $fillable = ['sign_id', 'level', 'level_name', 'criteria'];

    protected $casts = ['level' => 'integer'];

    public function sign(): BelongsTo
    {
        return $this->belongsTo(AbilityTalentSign::class, 'sign_id', 'sign_id');
    }
}

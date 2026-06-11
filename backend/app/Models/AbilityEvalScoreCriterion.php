<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 能力評価マスタ: 評価基準(0〜10点の判定基準)。
 */
class AbilityEvalScoreCriterion extends Model
{
    protected $table = 'ability_eval_score_criteria';

    protected $primaryKey = 'score';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['score', 'name', 'criteria', 'guardian_words', 'example', 'evidence'];

    protected $casts = ['score' => 'integer'];
}

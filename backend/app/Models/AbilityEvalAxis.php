<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 能力評価マスタ: 軸(S1〜S6 成長段階 / L1〜L4 到達水準 / P1〜P2 時期)。
 */
class AbilityEvalAxis extends Model
{
    protected $table = 'ability_eval_axes';

    protected $primaryKey = 'axis_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['axis_id', 'axis_type', 'name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 能力評価マスタ: 支援コード(SUP0〜SUP6)。提供した支援の種類と対応点数帯。
 */
class AbilitySupportCode extends Model
{
    protected $table = 'ability_support_codes';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'content', 'score_band', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];
}

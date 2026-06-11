<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 能力評価マスタ: 評価ツール(DEV/ADV/WRK/UNV)。全事業所共通の参照マスタ。
 */
class AbilityEvalTool extends Model
{
    protected $table = 'ability_eval_tools';

    protected $primaryKey = 'tool_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['tool_id', 'name', 'target', 'axis_type'];

    public function items(): HasMany
    {
        return $this->hasMany(AbilityEvalItem::class, 'tool_id', 'tool_id');
    }
}

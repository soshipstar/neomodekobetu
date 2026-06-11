<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価マスタ: 到達目安(項目×軸)。246件。
 */
class AbilityEvalBenchmark extends Model
{
    protected $table = 'ability_eval_benchmarks';

    protected $fillable = ['item_id', 'axis_id', 'benchmark'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalItem::class, 'item_id', 'item_id');
    }

    public function axis(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalAxis::class, 'axis_id', 'axis_id');
    }
}

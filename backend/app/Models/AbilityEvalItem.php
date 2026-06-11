<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 能力評価マスタ: 評価項目(例 DEV-1-1)。80項目。
 */
class AbilityEvalItem extends Model
{
    protected $table = 'ability_eval_items';

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['item_id', 'tool_id', 'domain', 'name', 'definition', 'perspective', 'source'];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalTool::class, 'tool_id', 'tool_id');
    }

    public function benchmarks(): HasMany
    {
        return $this->hasMany(AbilityEvalBenchmark::class, 'item_id', 'item_id');
    }
}

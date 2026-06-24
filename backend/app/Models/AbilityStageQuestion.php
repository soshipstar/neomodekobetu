<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価: 段階別の具体設問(項目×学年帯ごと)。
 *
 * 到達目安を根拠に AI 生成した「指導員が答えられる具体的な問い」と観察ヒント。
 * 日々の出題はこの問いを優先表示する(無ければ到達目安にフォールバック)。
 */
class AbilityStageQuestion extends Model
{
    protected $table = 'ability_stage_questions';

    protected $fillable = [
        'item_id', 'axis_id', 'question', 'hint', 'model', 'is_active', 'generated_at', 'reviewed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'generated_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalItem::class, 'item_id', 'item_id');
    }
}

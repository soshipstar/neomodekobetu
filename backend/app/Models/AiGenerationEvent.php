<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI学習基盤: 生成イベント(before の源泉)。generated_payload はマスク済で保存する。
 */
class AiGenerationEvent extends Model
{
    // generated_at(DBデフォルト useCurrent)のみ。created_at/updated_at 列は持たない。
    public $timestamps = false;

    protected $fillable = [
        'ai_generation_log_id', 'document_type', 'document_id', 'student_id', 'classroom_id',
        'company_id', 'user_id', 'generation_type', 'model', 'prompt_version',
        'sources_used', 'generated_payload', 'pii_masked', 'generated_at',
    ];

    protected $casts = [
        'sources_used' => 'array',
        'generated_payload' => 'array',
        'pii_masked' => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function revisionEvents(): HasMany
    {
        return $this->hasMany(AiRevisionEvent::class);
    }
}

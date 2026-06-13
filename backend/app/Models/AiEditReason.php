<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI学習基盤: 修正理由(イベント×理由。チップ選択 + 自由記述)。
 */
class AiEditReason extends Model
{
    const UPDATED_AT = null; // append-only

    protected $fillable = [
        'ai_revision_event_id', 'category_id', 'free_text', 'reason_source', 'source_ref', 'user_id', 'created_at',
    ];

    protected $casts = [
        'source_ref' => 'array',
        'created_at' => 'datetime',
    ];

    public function revisionEvent(): BelongsTo
    {
        return $this->belongsTo(AiRevisionEvent::class, 'ai_revision_event_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AiEditReasonCategory::class, 'category_id');
    }
}

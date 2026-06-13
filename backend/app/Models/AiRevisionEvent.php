<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI学習基盤: 人間修正イベント(after / Layer1原本)。学習の主単位(セクション単位)。
 * before_text/after_text は実名を含むため encrypted cast で保存時暗号化する。
 */
class AiRevisionEvent extends Model
{
    const UPDATED_AT = null; // append-only(created_at のみ)

    protected $fillable = [
        'company_id', 'classroom_id', 'student_id', 'document_type', 'document_id', 'section_key',
        'ai_generation_event_id', 'before_text', 'after_text', 'diff', 'change_ratio', 'changed',
        'edit_kind', 'editor_user_id', 'editor_role', 'sensitivity', 'created_at',
    ];

    protected $casts = [
        'before_text' => 'encrypted',
        'after_text' => 'encrypted',
        'diff' => 'array',
        'change_ratio' => 'float',
        'changed' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function generationEvent(): BelongsTo
    {
        return $this->belongsTo(AiGenerationEvent::class, 'ai_generation_event_id');
    }

    public function reasons(): HasMany
    {
        return $this->hasMany(AiEditReason::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

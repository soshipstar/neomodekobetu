<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StudentChatMessage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'room_id',
        'sender_id',
        'sender_type',
        'message',
        'is_deleted',
        'is_archived',
    ];

    protected function casts(): array
    {
        // created_at は TZ 付き ISO 8601 (Laravel デフォルト) で出力する。
        // "Y-m-d H:i:s" 固定だと UTC の wall clock が TZ マーカー無しで出力され、
        // チャット時刻が日本時間から 9 時間ズレて表示される。
        return [
            'is_deleted' => 'boolean',
            'is_archived' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<StudentChatRoom, self> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(StudentChatRoom::class, 'room_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', false);
    }
}

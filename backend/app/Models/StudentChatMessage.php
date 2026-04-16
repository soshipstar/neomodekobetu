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
        return [
            'is_deleted' => 'boolean',
            'is_archived' => 'boolean',
            'created_at' => 'datetime:Y-m-d H:i:s',
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

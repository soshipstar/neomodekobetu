<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ChatMessage extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'room_id',
        'sender_id',
        'sender_type',
        'message',
        'message_type',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'attachment_mime',
        'meeting_request_id',
        'is_deleted',
        'deleted_at',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
            'is_archived' => 'boolean',
            'deleted_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<ChatRoom, self> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /** @return HasMany<ChatMessageStaffRead> */
    public function staffReads(): HasMany
    {
        return $this->hasMany(ChatMessageStaffRead::class, 'message_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Exclude soft-deleted messages (using is_deleted flag instead of SoftDeletes).
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', false);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function hasAttachment(): bool
    {
        return ! empty($this->attachment_path);
    }
}

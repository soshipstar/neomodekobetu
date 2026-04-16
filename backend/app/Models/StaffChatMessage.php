<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StaffChatMessage extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'room_id',
        'sender_id',
        'message',
        'attachment_path',
        'attachment_original_name',
        'attachment_size',
        'is_deleted',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted'      => 'boolean',
            'is_archived'     => 'boolean',
            'attachment_size' => 'integer',
            'created_at'      => 'datetime:Y-m-d H:i:s',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<StaffChatRoom, self> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(StaffChatRoom::class, 'room_id');
    }

    /** @return BelongsTo<User, self> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

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

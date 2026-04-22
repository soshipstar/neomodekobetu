<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRoomPin extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'staff_id',
        'pinned_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
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

    /** @return BelongsTo<User, self> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}

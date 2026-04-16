<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageStaffRead extends Model
{
    public $timestamps = false;

    protected $table = 'chat_message_staff_reads';

    protected $fillable = [
        'message_id',
        'staff_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<ChatMessage, self> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /** @return BelongsTo<User, self> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}

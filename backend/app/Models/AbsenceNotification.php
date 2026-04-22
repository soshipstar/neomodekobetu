<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceNotification extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'absence_date',
        'reason',
        'message_id',
        'makeup_request_date',
        'makeup_status',
        'makeup_approved_by',
        'makeup_approved_at',
        'makeup_note',
    ];

    protected function casts(): array
    {
        return [
            'absence_date' => 'date:Y-m-d',
            'makeup_request_date' => 'date:Y-m-d',
            'makeup_approved_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<ChatMessage, self> */
    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /** @return BelongsTo<User, self> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'makeup_approved_by');
    }
}

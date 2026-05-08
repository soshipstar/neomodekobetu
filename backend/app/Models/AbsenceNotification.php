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
        // 体調関連 (保護者入力)
        'body_temperature',
        'hospital_visit',
        'symptom_abdominal_pain',
        'symptom_headache',
        'symptom_sore_throat',
        'symptom_cough',
        'symptom_sneeze',
        'symptom_runny_nose',
        'other_concerns',
        // アドバイス (スタッフ入力)
        'advice',
        'advice_by',
        'advice_at',
    ];

    protected function casts(): array
    {
        return [
            'absence_date'           => 'date:Y-m-d',
            'makeup_request_date'    => 'date:Y-m-d',
            'makeup_approved_at'     => 'datetime',
            'body_temperature'       => 'decimal:1',
            'hospital_visit'         => 'boolean',
            'symptom_abdominal_pain' => 'boolean',
            'symptom_headache'       => 'boolean',
            'symptom_sore_throat'    => 'boolean',
            'symptom_cough'          => 'boolean',
            'symptom_sneeze'         => 'boolean',
            'symptom_runny_nose'     => 'boolean',
            'advice_at'              => 'datetime',
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

    /** @return BelongsTo<User, self> アドバイスを入力したスタッフ */
    public function adviceAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advice_by');
    }
}

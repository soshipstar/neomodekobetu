<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceResponseRecord extends Model
{
    protected $fillable = [
        'student_id',
        'classroom_id',
        'absence_date',
        'absence_notification_id',
        'absence_reason',
        'response_content',
        'contact_method',
        'contact_content',
        'staff_id',
        'is_sent',
        'sent_at',
        'guardian_confirmed',
        'guardian_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'absence_date' => 'date',
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function absenceNotification(): BelongsTo
    {
        return $this->belongsTo(AbsenceNotification::class);
    }
}

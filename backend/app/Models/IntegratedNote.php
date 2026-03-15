<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegratedNote extends Model
{
    protected $fillable = [
        'daily_record_id',
        'student_id',
        'integrated_content',
        'is_sent',
        'sent_at',
        'guardian_confirmed',
        'guardian_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
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

    /** @return BelongsTo<DailyRecord, self> */
    public function dailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class, 'daily_record_id');
    }
}

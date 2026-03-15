<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRecord extends Model
{
    protected $fillable = [
        'daily_record_id',
        'student_id',
        'health_life',
        'motor_sensory',
        'cognitive_behavior',
        'language_communication',
        'social_relations',
        'notes',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<DailyRecord, self> */
    public function dailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class, 'daily_record_id');
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

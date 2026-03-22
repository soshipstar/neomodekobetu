<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentInterview extends Model
{
    protected $fillable = [
        'student_id',
        'classroom_id',
        'interview_date',
        'interviewer_id',
        'interview_content',
        'child_wish',
        'check_school',
        'check_school_notes',
        'check_home',
        'check_home_notes',
        'check_troubles',
        'check_troubles_notes',
        'other_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'check_school' => 'boolean',
            'check_home' => 'boolean',
            'check_troubles' => 'boolean',
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

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<User, self> */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}

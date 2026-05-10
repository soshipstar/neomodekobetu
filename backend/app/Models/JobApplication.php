<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $fillable = [
        'student_id', 'classroom_id', 'company_name', 'industry', 'job_title',
        'employment_type', 'application_date', 'source', 'status',
        'interview_date', 'result_date', 'result_notes', 'feedback', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date:Y-m-d',
            'interview_date'   => 'date:Y-m-d',
            'result_date'      => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
}

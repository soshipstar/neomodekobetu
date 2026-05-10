<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInternship extends Model
{
    protected $fillable = [
        'student_id', 'classroom_id', 'company_name', 'contact_person', 'contact_phone',
        'start_date', 'end_date', 'total_days', 'internship_type', 'purpose', 'plan_content',
        'daily_logs', 'company_evaluation', 'attitude_score', 'skill_score', 'communication_score',
        'staff_evaluation', 'outcome', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date'   => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
}

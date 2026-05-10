<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPlacement extends Model
{
    protected $fillable = [
        'student_id', 'classroom_id', 'company_name', 'job_title', 'start_date', 'end_date',
        'employment_type', 'monthly_salary', 'weekly_hours', 'status',
        'reasonable_accommodations', 'next_followup_date', 'separation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date'        => 'date:Y-m-d',
            'end_date'          => 'date:Y-m-d',
            'next_followup_date' => 'date:Y-m-d',
            'monthly_salary'    => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }

    /** @return HasMany<JobPlacementContact> */
    public function contacts(): HasMany { return $this->hasMany(JobPlacementContact::class); }
}

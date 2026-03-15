<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequest extends Model
{
    protected $fillable = [
        'classroom_id',
        'student_id',
        'guardian_id',
        'staff_id',
        'purpose',
        'purpose_detail',
        'related_plan_id',
        'related_monitoring_id',
        'candidate_dates',
        'confirmed_date',
        'meeting_notes',
        'meeting_guidance',
        'status',
        'confirmed_by',
        'confirmed_at',
        'is_completed',
        'completed_at',
        'guardian_counter_message',
        'staff_counter_message',
    ];

    protected function casts(): array
    {
        return [
            'candidate_dates' => 'array',
            'confirmed_date' => 'datetime',
            'confirmed_at' => 'datetime',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, self> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return BelongsTo<User, self> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<IndividualSupportPlan, self> */
    public function relatedPlan(): BelongsTo
    {
        return $this->belongsTo(IndividualSupportPlan::class, 'related_plan_id');
    }

    /** @return BelongsTo<MonitoringRecord, self> */
    public function relatedMonitoring(): BelongsTo
    {
        return $this->belongsTo(MonitoringRecord::class, 'related_monitoring_id');
    }
}

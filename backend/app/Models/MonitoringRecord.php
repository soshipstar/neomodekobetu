<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'student_id',
        'classroom_id',
        'monitoring_date',
        'overall_comment',
        'short_term_goal_achievement',
        'long_term_goal_achievement',
        'is_official',
        'guardian_confirmed',
        'guardian_confirmed_at',
        'staff_signature',
        'guardian_signature',
        'created_by',
        'student_name',
        'short_term_goal_comment',
        'long_term_goal_comment',
        'is_draft',
        'is_hidden',
        'guardian_signature_date',
        'staff_signature_date',
        'staff_signer_name',
    ];

    protected function casts(): array
    {
        return [
            'monitoring_date' => 'date',
            'is_official' => 'boolean',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
            'is_draft' => 'boolean',
            'is_hidden' => 'boolean',
            'guardian_signature_date' => 'date',
            'staff_signature_date' => 'date',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<IndividualSupportPlan, self> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(IndividualSupportPlan::class, 'plan_id');
    }

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

    /** @return HasMany<MonitoringDetail> */
    public function details(): HasMany
    {
        return $this->hasMany(MonitoringDetail::class, 'monitoring_id');
    }

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

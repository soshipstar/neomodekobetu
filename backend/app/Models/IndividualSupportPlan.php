<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class IndividualSupportPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'classroom_id',
        'student_name',
        'created_date',
        'life_intention',
        'overall_policy',
        'long_term_goal',
        'short_term_goal',
        'consent_date',
        'is_official',
        'staff_signature',
        'staff_signature_date',
        'guardian_signature',
        'guardian_signature_date',
        'guardian_review_comment',
        'guardian_reviewed_at',
        'plan_source_period',
        'start_type',
        'basis_content',
        'status',
        'created_by',
        'manager_name',
        'long_term_goal_date',
        'short_term_goal_date',
        'is_hidden',
        'guardian_confirmed',
        'guardian_confirmed_at',
        'source_monitoring_id',
        'basis_generated_at',
        'staff_signer_name',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date',
            'consent_date' => 'date',
            'is_official' => 'boolean',
            'staff_signature_date' => 'date',
            'guardian_signature_date' => 'date',
            'guardian_reviewed_at' => 'datetime',
            'long_term_goal_date' => 'date',
            'short_term_goal_date' => 'date',
            'is_hidden' => 'boolean',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
            'basis_generated_at' => 'datetime',
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

    /** @return HasMany<SupportPlanDetail> */
    public function details(): HasMany
    {
        return $this->hasMany(SupportPlanDetail::class, 'plan_id');
    }

    /** @return HasMany<MonitoringRecord> */
    public function monitoringRecords(): HasMany
    {
        return $this->hasMany(MonitoringRecord::class, 'plan_id');
    }

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}

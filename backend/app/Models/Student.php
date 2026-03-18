<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'student_name',
        'username',
        'password_hash',
        'birth_date',
        'grade_level',
        'grade_adjustment',
        'guardian_id',
        'support_start_date',
        'kakehashi_initial_date',
        'support_plan_start_type',
        'notes',
        'is_active',
        'status',
        'withdrawal_date',
        'scheduled_monday',
        'scheduled_tuesday',
        'scheduled_wednesday',
        'scheduled_thursday',
        'scheduled_friday',
        'scheduled_saturday',
        'scheduled_sunday',
        'password_plain',
        'hide_initial_monitoring',
        'desired_start_date',
        'desired_weekly_count',
        'waiting_notes',
        'last_login_at',
        'desired_monday',
        'desired_tuesday',
        'desired_wednesday',
        'desired_thursday',
        'desired_friday',
        'desired_saturday',
        'desired_sunday',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'support_start_date' => 'date',
            'kakehashi_initial_date' => 'date',
            'withdrawal_date' => 'date',
            'is_active' => 'boolean',
            'hide_initial_monitoring' => 'boolean',
            'scheduled_monday' => 'boolean',
            'scheduled_tuesday' => 'boolean',
            'scheduled_wednesday' => 'boolean',
            'scheduled_thursday' => 'boolean',
            'scheduled_friday' => 'boolean',
            'scheduled_saturday' => 'boolean',
            'scheduled_sunday' => 'boolean',
            'desired_start_date' => 'date',
            'desired_monday' => 'boolean',
            'desired_tuesday' => 'boolean',
            'desired_wednesday' => 'boolean',
            'desired_thursday' => 'boolean',
            'desired_friday' => 'boolean',
            'desired_saturday' => 'boolean',
            'desired_sunday' => 'boolean',
            'last_login_at' => 'datetime',
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

    /** @return BelongsTo<User, self> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return HasMany<ChatRoom> */
    public function chatRooms(): HasMany
    {
        return $this->hasMany(ChatRoom::class);
    }

    /** @return HasMany<IndividualSupportPlan> */
    public function supportPlans(): HasMany
    {
        return $this->hasMany(IndividualSupportPlan::class);
    }

    /** @return HasMany<MonitoringRecord> */
    public function monitoringRecords(): HasMany
    {
        return $this->hasMany(MonitoringRecord::class);
    }

    /** @return HasMany<KakehashiPeriod> */
    public function kakehashiPeriods(): HasMany
    {
        return $this->hasMany(KakehashiPeriod::class);
    }

    /** @return HasMany<AbsenceNotification> */
    public function absenceNotifications(): HasMany
    {
        return $this->hasMany(AbsenceNotification::class);
    }

    /** @return HasMany<StudentRecord> */
    public function dailyRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class);
    }

    /** @return HasMany<StudentInterview> */
    public function interviews(): HasMany
    {
        return $this->hasMany(StudentInterview::class);
    }

    /** @return HasOne<StudentChatRoom> */
    public function studentChatRoom(): HasOne
    {
        return $this->hasOne(StudentChatRoom::class);
    }

    /** @return HasMany<MeetingRequest> */
    public function meetingRequests(): HasMany
    {
        return $this->hasMany(MeetingRequest::class);
    }

    /** @return HasMany<IntegratedNote> */
    public function integratedNotes(): HasMany
    {
        return $this->hasMany(IntegratedNote::class);
    }

    /** @return HasMany<FacilityEvaluation> */
    public function facilityEvaluations(): HasMany
    {
        return $this->hasMany(FacilityEvaluation::class);
    }

    /** @return HasMany<AdditionalUsage> */
    public function additionalUsages(): HasMany
    {
        return $this->hasMany(AdditionalUsage::class);
    }

    /** @return HasMany<SubmissionRequest> */
    public function submissionRequests(): HasMany
    {
        return $this->hasMany(SubmissionRequest::class);
    }

    /** @return HasMany<StudentSubmission> */
    public function studentSubmissions(): HasMany
    {
        return $this->hasMany(StudentSubmission::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByClassroom(Builder $query, int $classroomId): Builder
    {
        return $query->where('classroom_id', $classroomId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get scheduled days as an array of day names.
     */
    public function getScheduledDays(): array
    {
        $days = [];
        $map = [
            'monday', 'tuesday', 'wednesday', 'thursday',
            'friday', 'saturday', 'sunday',
        ];

        foreach ($map as $day) {
            if ($this->{"scheduled_{$day}"}) {
                $days[] = $day;
            }
        }

        return $days;
    }
}

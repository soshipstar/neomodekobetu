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
        'long_term_goal_date',
        'short_term_goal_date',
        'consent_date',
        'consent_name',
        'is_draft',
        'is_official',
        'status',
        'guardian_confirmed',
        'guardian_confirmed_at',
        'guardian_review_comment',
        'guardian_review_comment_at',
        'staff_signature',
        'staff_signature_image',
        'staff_signature_date',
        'staff_signer_name',
        'guardian_signature',
        'guardian_signature_image',
        'guardian_signature_date',
        'guardian_reviewed_at',
        'plan_source_period',
        'start_type',
        'basis_content',
        'created_by',
        'manager_name',
        'is_hidden',
        'source_monitoring_id',
        'basis_generated_at',
        'service_type_data',
        // Phase C: サイクル管理
        'cycle_number',
        'plan_period_start',
        'plan_period_end',
        'next_monitoring_due_date',
        'next_plan_due_date',
        // 原案 / 本案 分離 (2026-05-17 追加)
        // draft_xxx = 原案テキスト本体。既存 life_intention 等は本案として運用。
        'draft_life_intention',
        'draft_overall_policy',
        'draft_long_term_goal',
        'draft_short_term_goal',
        'draft_saved_at',
        'official_saved_at',
        // 原案→本案の変更説明 (AI 自動生成、印刷物には含めない)
        'revision_notes',
        'revision_notes_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date:Y-m-d',
            'consent_date' => 'date:Y-m-d',
            'plan_period_start' => 'date:Y-m-d',
            'plan_period_end'   => 'date:Y-m-d',
            'next_monitoring_due_date' => 'date:Y-m-d',
            'next_plan_due_date'       => 'date:Y-m-d',
            'is_draft' => 'boolean',
            'is_official' => 'boolean',
            'staff_signature_date' => 'date:Y-m-d',
            'guardian_signature_date' => 'date:Y-m-d',
            'guardian_reviewed_at' => 'datetime',
            'guardian_review_comment_at' => 'datetime',
            'long_term_goal_date' => 'date:Y-m-d',
            'short_term_goal_date' => 'date:Y-m-d',
            'is_hidden' => 'boolean',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
            'basis_generated_at' => 'datetime',
            'service_type_data' => 'array',
            // 原案/本案 分離フィールド
            'draft_saved_at'              => 'datetime',
            'official_saved_at'           => 'datetime',
            'revision_notes_generated_at' => 'datetime',
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

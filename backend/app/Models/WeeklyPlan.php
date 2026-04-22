<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyPlan extends Model
{
    protected $fillable = [
        'classroom_id',
        'student_id',
        'week_start_date',
        'plan_content',
        'weekly_goal',
        'shared_goal',
        'must_do',
        'should_do',
        'want_to_do',
        'weekly_goal_achievement',
        'weekly_goal_comment',
        'shared_goal_achievement',
        'shared_goal_comment',
        'must_do_achievement',
        'must_do_comment',
        'should_do_achievement',
        'should_do_comment',
        'want_to_do_achievement',
        'want_to_do_comment',
        'daily_achievement',
        'overall_comment',
        'evaluated_at',
        'plan_data',
        'status',
        'created_by',
        'created_by_type',
        'evaluated_by_type',
        'evaluated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date'  => 'date:Y-m-d',
            'plan_content'     => 'array',
            'plan_data'        => 'array',
            'daily_achievement' => 'array',
            'evaluated_at'     => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WeeklyPlanComment::class, 'plan_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WeeklyPlanSubmission::class, 'weekly_plan_id');
    }
}

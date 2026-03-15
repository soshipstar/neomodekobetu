<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyPlan extends Model
{
    protected $fillable = [
        'classroom_id',
        'week_start_date',
        'plan_content',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'plan_content' => 'array',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<WeeklyPlanComment> */
    public function comments(): HasMany
    {
        return $this->hasMany(WeeklyPlanComment::class, 'plan_id');
    }

    /** @return HasMany<WeeklyPlanSubmission> */
    public function submissions(): HasMany
    {
        return $this->hasMany(WeeklyPlanSubmission::class, 'weekly_plan_id');
    }
}

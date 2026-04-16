<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportPlanDetail extends Model
{
    protected $appends = ['support_goal'];

    protected $fillable = [
        'plan_id',
        'domain',
        'category',
        'current_status',
        'goal',
        'support_content',
        'achievement_status',
        'sort_order',
        'sub_category',
        'achievement_date',
        'staff_organization',
        'notes',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'achievement_date' => 'date:Y-m-d',
            'priority' => 'integer',
        ];
    }

    // =========================================================================
    // Accessors (legacy compatibility: support_goal is alias for goal)
    // =========================================================================

    /**
     * support_goal accessor – legacy code and frontend use support_goal,
     * but the DB column is `goal`.
     */
    public function getSupportGoalAttribute(): ?string
    {
        return $this->attributes['goal'] ?? null;
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<IndividualSupportPlan, self> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(IndividualSupportPlan::class, 'plan_id');
    }
}

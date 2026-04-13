<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPlanComment extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'plan_id',
        'user_id',
        'commenter_type',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<WeeklyPlan, self> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class, 'plan_id');
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

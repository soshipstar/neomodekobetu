<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySupportPlan extends Model
{
    protected $fillable = [
        'activity_name',
        'activity_date',
        'plan_type',
        'target_grade',
        'activity_purpose',
        'activity_content',
        'tags',
        'day_of_week',
        'five_domains_consideration',
        'other_notes',
        'total_duration',
        'activity_schedule',
        'staff_id',
        'classroom_id',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date:Y-m-d',
            'activity_schedule' => 'array',
            'total_duration' => 'integer',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getTagsArrayAttribute(): array
    {
        return $this->tags ? explode(',', $this->tags) : [];
    }

    public function getDayOfWeekArrayAttribute(): array
    {
        return $this->day_of_week ? explode(',', $this->day_of_week) : [];
    }

    public function getTargetGradeArrayAttribute(): array
    {
        return $this->target_grade ? explode(',', $this->target_grade) : [];
    }
}

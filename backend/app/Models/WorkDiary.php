<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDiary extends Model
{
    protected $fillable = [
        'classroom_id',
        'diary_date',
        'previous_day_review',
        'daily_communication',
        'daily_roles',
        'prev_day_children_status',
        'children_special_notes',
        'other_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'diary_date' => 'date:Y-m-d',
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

    /** @return BelongsTo<User, self> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

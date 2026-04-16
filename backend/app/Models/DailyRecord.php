<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'record_date',
        'activity_name',
        'common_activity',
        'staff_id',
        'support_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date:Y-m-d',
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
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return HasMany<StudentRecord> */
    public function studentRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class, 'daily_record_id');
    }

    /** @return HasMany<IntegratedNote> */
    public function integratedNotes(): HasMany
    {
        return $this->hasMany(IntegratedNote::class, 'daily_record_id');
    }
}

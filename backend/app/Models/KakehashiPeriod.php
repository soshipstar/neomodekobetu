<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class KakehashiPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'period_name',
        'start_date',
        'end_date',
        'submission_deadline',
        'is_active',
        'is_auto_generated',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'submission_deadline' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'is_auto_generated' => 'boolean',
            'created_at' => 'datetime:Y-m-d H:i:s',
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

    /** @return HasMany<KakehashiStaff> */
    public function staffEntries(): HasMany
    {
        return $this->hasMany(KakehashiStaff::class, 'period_id');
    }

    /** @return HasMany<KakehashiGuardian> */
    public function guardianEntries(): HasMany
    {
        return $this->hasMany(KakehashiGuardian::class, 'period_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

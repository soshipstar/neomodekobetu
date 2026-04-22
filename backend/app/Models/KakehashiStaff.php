<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KakehashiStaff extends Model
{
    protected $table = 'kakehashi_staff';

    protected $fillable = [
        'period_id',
        'student_id',
        'staff_id',
        'student_wish',
        'short_term_goal',
        'long_term_goal',
        'health_life',
        'motor_sensory',
        'cognitive_behavior',
        'language_communication',
        'social_relations',
        'is_submitted',
        'submitted_at',
        'guardian_confirmed',
        'guardian_confirmed_at',
        'other_challenges',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'is_submitted' => 'boolean',
            'submitted_at' => 'datetime',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
            'is_hidden' => 'boolean',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<KakehashiPeriod, self> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(KakehashiPeriod::class, 'period_id');
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, self> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KakehashiGuardian extends Model
{
    protected $table = 'kakehashi_guardian';

    protected $fillable = [
        'period_id',
        'student_id',
        'guardian_id',
        'home_situation',
        'concerns',
        'requests',
        'is_submitted',
        'submitted_at',
        'student_wish',
        'home_challenges',
        'short_term_goal',
        'long_term_goal',
        'domain_health_life',
        'domain_motor_sensory',
        'domain_cognitive_behavior',
        'domain_language_communication',
        'domain_social_relations',
        'other_challenges',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'is_submitted' => 'boolean',
            'submitted_at' => 'datetime:Y-m-d H:i:s',
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
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }
}

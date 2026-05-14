<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRecord extends Model
{
    protected $fillable = [
        'daily_record_id',
        'student_id',
        'health_life',
        'motor_sensory',
        'cognitive_behavior',
        'language_communication',
        'social_relations',
        'notes',
        // 領域別の目標引用設定 (2026-05-14 追加・置換)
        // 形式: { domain_key: { quoted: bool, goal_snapshot: string|null } }
        'domain_goal_quotes',
        // 個別支援計画の短期・長期目標に対するコメント (2026-05-14 追加)
        'short_term_goal_comment',
        'long_term_goal_comment',
    ];

    protected function casts(): array
    {
        return [
            'domain_goal_quotes' => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<DailyRecord, self> */
    public function dailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class, 'daily_record_id');
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

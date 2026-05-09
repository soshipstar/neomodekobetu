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
        'strengths',
        'service_type_data',
    ];

    protected $casts = [
        'strengths'         => 'array',
        'service_type_data' => 'array',
    ];

    /**
     * 強み(才能)チェックの固定キー一覧。連絡帳 UI/PDF/集計で参照する正規順序。
     */
    public const STRENGTH_KEYS = [
        '集中力',
        '持続力',
        '丁寧さ',
        '発想力',
        '観察力',
        '思いやり',
        '情報処理の速さ',
        '手先の器用さ',
        '自分で選ぶ力',
        'コミュニケーションの工夫',
    ];

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

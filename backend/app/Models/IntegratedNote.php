<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IntegratedNote extends Model
{
    protected $fillable = [
        'daily_record_id',
        'student_id',
        'integrated_content',
        'is_sent',
        'sent_at',
        'guardian_confirmed',
        'guardian_confirmed_at',
        // AISI R5 (2026-05-17): AI 関与情報 (HITL + 透明性)
        'ai_assisted',
        'ai_review_status',
        'ai_reviewed_by',
        'ai_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime',
            'ai_assisted' => 'boolean',
            'ai_reviewed_at' => 'datetime',
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

    /** @return BelongsTo<DailyRecord, self> */
    public function dailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class, 'daily_record_id');
    }

    /**
     * 連絡帳に添付された写真 (classroom_photos への参照)
     * @return BelongsToMany<ClassroomPhoto>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(ClassroomPhoto::class, 'integrated_note_photos', 'integrated_note_id', 'classroom_photo_id')
            ->withPivot('sort_order')
            ->orderBy('integrated_note_photos.sort_order');
    }
}

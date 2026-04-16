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
    ];

    protected function casts(): array
    {
        return [
            'is_sent' => 'boolean',
            'sent_at' => 'datetime:Y-m-d H:i:s',
            'guardian_confirmed' => 'boolean',
            'guardian_confirmed_at' => 'datetime:Y-m-d H:i:s',
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

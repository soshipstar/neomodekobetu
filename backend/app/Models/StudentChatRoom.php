<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentChatRoom extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        // TZ マーカー付き ISO 8601 (Laravel デフォルト) で出力する。
        return [
            'last_message_at' => 'datetime',
            'created_at' => 'datetime',
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

    /** @return HasMany<StudentChatMessage> */
    public function messages(): HasMany
    {
        return $this->hasMany(StudentChatMessage::class, 'room_id');
    }
}

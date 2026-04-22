<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionRequest extends Model
{
    protected $fillable = [
        'room_id',
        'student_id',
        'guardian_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'is_completed',
        'completed_at',
        'completed_note',
        'attachment_path',
        'attachment_original_name',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'attachment_size' => 'integer',
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

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<ChatRoom, self> */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }
}

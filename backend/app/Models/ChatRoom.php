<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ChatRoom extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'guardian_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
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

    /** @return BelongsTo<User, self> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return HasMany<ChatMessage> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    /** @return HasMany<ChatRoomPin> */
    public function pins(): HasMany
    {
        return $this->hasMany(ChatRoomPin::class, 'room_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to filter chat rooms accessible by a given user.
     * Guardians see only their own rooms; staff/admin see rooms in their classroom.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isGuardian()) {
            return $query->where('guardian_id', $user->id);
        }

        // Staff and admin see rooms for students in their classroom
        return $query->whereHas('student', function (Builder $q) use ($user) {
            $q->where('classroom_id', $user->classroom_id);
        });
    }
}

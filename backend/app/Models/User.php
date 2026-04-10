<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'classroom_id',
        'username',
        'password',
        'password_plain',
        'full_name',
        'email',
        'user_type',
        'is_master',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'password_plain',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_master' => 'boolean',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * 現在アクティブな教室（単一）
     * @return BelongsTo<Classroom, self>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * 所属している全ての教室（複数）
     * @return BelongsToMany<Classroom>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_user')->withTimestamps();
    }

    /**
     * このユーザーがアクセス可能な教室IDのリストを取得
     * @return array<int>
     */
    public function accessibleClassroomIds(): array
    {
        $ids = $this->classrooms()->pluck('classrooms.id')->toArray();
        // classroom_userになくてもusers.classroom_idがあれば含める（後方互換）
        if ($this->classroom_id && !in_array($this->classroom_id, $ids)) {
            $ids[] = $this->classroom_id;
        }
        return $ids;
    }

    /**
     * Students that this user is guardian for.
     *
     * @return HasMany<Student>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_id');
    }

    /** @return HasMany<ChatMessage> */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /** @return HasMany<ChatMessageStaffRead> */
    public function staffReads(): HasMany
    {
        return $this->hasMany(ChatMessageStaffRead::class, 'staff_id');
    }

    /** @return HasMany<ChatRoomPin> */
    public function pinnedRooms(): HasMany
    {
        return $this->hasMany(ChatRoomPin::class, 'staff_id');
    }

    /** @return HasMany<Notification> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return HasMany<AuditLog> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** @return HasMany<AiGenerationLog> */
    public function aiGenerationLogs(): HasMany
    {
        return $this->hasMany(AiGenerationLog::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('user_type', 'staff');
    }

    public function scopeGuardian(Builder $query): Builder
    {
        return $query->where('user_type', 'guardian');
    }

    public function scopeAdmin(Builder $query): Builder
    {
        return $query->where('user_type', 'admin');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->user_type === 'staff';
    }

    public function isGuardian(): bool
    {
        return $this->user_type === 'guardian';
    }

    public function isMaster(): bool
    {
        return $this->is_master === true;
    }
}

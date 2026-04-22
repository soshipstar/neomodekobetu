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
        'is_company_admin',
        'is_active',
        'notification_preferences',
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
            'is_company_admin' => 'boolean',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 指定カテゴリの通知が許可されているか判定する。
     * notification_preferences が null または該当キーが無ければデフォルトで有効。
     */
    public function acceptsNotification(string $category): bool
    {
        $prefs = $this->notification_preferences ?? [];
        if (!is_array($prefs)) {
            return true;
        }
        // 未設定キーは有効扱い
        if (!array_key_exists($category, $prefs)) {
            return true;
        }
        return (bool) $prefs[$category];
    }

    /**
     * 所属企業を classroom 経由で取得（accessor）。
     * users.company_id カラムは正規化のため削除済み。
     */
    public function getCompanyAttribute(): ?Company
    {
        return $this->classroom?->company;
    }

    /**
     * 所属企業 ID を classroom 経由で取得（accessor）。
     */
    public function getCompanyIdAttribute(): ?int
    {
        // eager load 済みなら relation から、そうでなければ classroom をロードして取得
        if ($this->relationLoaded('classroom') && $this->classroom) {
            return $this->classroom->company_id;
        }

        if ($this->classroom_id) {
            return $this->classroom?->company_id;
        }

        return null;
    }

    /**
     * マスター管理者（複数企業を統括）
     */
    public function isMasterAdmin(): bool
    {
        return $this->user_type === 'admin' && $this->is_master;
    }

    /**
     * 企業管理者（1企業の全教室を管理）
     */
    public function isCompanyAdmin(): bool
    {
        return $this->user_type === 'admin' && $this->is_company_admin;
    }

    /**
     * 教室追加権限があるか（マスター管理者のみ）
     */
    public function canCreateClassroom(): bool
    {
        return $this->isMasterAdmin();
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
     *
     * - マスター管理者 (is_master=true) は全教室にアクセス可能なので、
     *   存在する全 classroom の id を返す
     * - 保護者 (user_type=guardian) は自身の classroom_user ではなく、
     *   担当する全児童の在籍教室集合を返す（子どもたちの所属教室を束ねる）
     * - それ以外（スタッフ・通常管理者）は classroom_user ピボット +
     *   後方互換用の users.classroom_id
     *
     * @return array<int>
     */
    /**
     * データ表示用: 現在のアクティブ教室のみを返す。
     * 企業管理者は教室切り替えで classroom_id を変更してコンテキストを切り替える。
     *
     * @return array<int>
     */
    public function accessibleClassroomIds(): array
    {
        if ($this->is_master === true) {
            return Classroom::query()->pluck('id')->all();
        }

        if ($this->user_type === 'guardian') {
            return Student::where('guardian_id', $this->id)
                ->whereNotNull('classroom_id')
                ->pluck('classroom_id')
                ->unique()
                ->values()
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        // スタッフ・管理者・企業管理者: 現在のアクティブ教室のみ
        return $this->classroom_id ? [$this->classroom_id] : [];
    }

    /**
     * 認可・教室切替用: ユーザーがアクセス権限を持つ全教室を返す。
     * 教室切り替えドロップダウンや、個別レコードのアクセス認可に使用。
     *
     * @return array<int>
     */
    public function switchableClassroomIds(): array
    {
        if ($this->is_master === true) {
            return Classroom::query()->pluck('id')->all();
        }

        if ($this->isCompanyAdmin()) {
            $companyId = $this->company_id;
            if ($companyId) {
                return Classroom::where('company_id', $companyId)->pluck('id')->all();
            }
        }

        if ($this->user_type === 'guardian') {
            return Student::where('guardian_id', $this->id)
                ->whereNotNull('classroom_id')
                ->pluck('classroom_id')
                ->unique()
                ->values()
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        // スタッフ: classroom_user ピボット + users.classroom_id の和集合
        // 後方互換: ピボット未登録の既存ユーザーは classroom_id だけで切替可能（単一所属）
        // 同一企業境界は管理UI (UserClassroomController::sync) で強制済みのため、ここでは再検証しない
        $pivotIds = $this->classrooms()->pluck('classrooms.id')->map(fn ($v) => (int) $v)->all();
        $ids = $this->classroom_id ? array_merge($pivotIds, [(int) $this->classroom_id]) : $pivotIds;

        return array_values(array_unique($ids));
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

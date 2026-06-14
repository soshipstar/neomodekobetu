<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'target_table',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * テナント分離(rank6): 作成時に company_id を実行者の所属施設で自動補完する。
     * 多数の AuditLog::create 呼出を変更せずにテナントを付与する(明示指定があれば尊重)。
     * システム/マスター実行(施設なし)では null のまま(=非マスターには見せない)。
     */
    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            if ($log->company_id === null) {
                $log->company_id = Auth::user()?->company_id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

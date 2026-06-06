<?php

namespace App\Models;

use App\Models\Concerns\HashChainable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HashChainable;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'target_table',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        // AISI R9 (2026-05-17): ハッシュチェーンによる改ざん防止カラム。
        // 自動計算 (HashChainable::bootHashChainable creating フック) なので
        // 通常は呼出側で指定しないが、backfill コマンドから書き込めるよう fillable に含める。
        'row_hash',
        'prev_row_hash',
    ];

    /**
     * HashChainable が row_hash 計算に使用するフィールド。
     * これらが「改ざん検知の対象」になる。
     */
    public array $hashFields = [
        'user_id', 'action', 'target_table', 'target_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
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

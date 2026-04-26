<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * マスター管理者の操作監査ログ。append-only（updated_at は使わない）。
 */
class MasterAdminAuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'master_user_id',
        'company_id',
        'action',
        'before',
        'after',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
        ];
    }

    /** @return BelongsTo<User, MasterAdminAuditLog> */
    public function masterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_user_id');
    }

    /** @return BelongsTo<Company, MasterAdminAuditLog> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

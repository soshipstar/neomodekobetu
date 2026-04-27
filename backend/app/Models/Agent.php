<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 代理店マスタ。販売チャネルが「代理店経由」の企業を束ねる。
 */
class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'default_commission_rate',
        'bank_info',
        'contract_document_path',
        'contract_terms',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_commission_rate' => 'decimal:4',
            'bank_info' => 'array',
        ];
    }

    /** @return HasMany<Company> */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /** @return HasMany<User> 代理店スタッフユーザー */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<AgentPayout> */
    public function payouts(): HasMany
    {
        return $this->hasMany(AgentPayout::class);
    }
}

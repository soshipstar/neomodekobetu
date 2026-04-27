<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Laravel\Cashier\Billable;

class Company extends Model
{
    use Billable;
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        // Cashier Billable 標準カラム（テスト/シーダーから手動設定するため fillable）
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        // 自前の課金管理カラム
        'subscription_status',
        'current_price_id',
        'custom_amount',
        'is_custom_pricing',
        'tax_inclusive',
        'current_period_end',
        'cancel_at_period_end',
        'contract_started_at',
        'contract_notes',
        'contract_document_path',
        'display_settings',
        'feature_flags',
        'individual_terms',
        // 販売チャネル: 代理店経由の場合のみ agent_id をセット。直販なら NULL
        'agent_id',
        'commission_rate_override',
        'agent_assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_custom_pricing' => 'boolean',
            'tax_inclusive' => 'boolean',
            'cancel_at_period_end' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
            'contract_started_at' => 'datetime',
            'display_settings' => 'array',
            'feature_flags' => 'array',
            'individual_terms' => 'array',
            'commission_rate_override' => 'decimal:4',
            'agent_assigned_at' => 'datetime',
        ];
    }

    /** @return HasMany<Classroom> */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * 所属ユーザー（classrooms を経由）。
     * users.company_id カラムは削除済みのため hasManyThrough で導出する。
     *
     * @return HasManyThrough<User>
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Classroom::class);
    }

    /** @return HasMany<Invoice> */
    public function invoiceRecords(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return BelongsTo<Agent, Company> 代理店経由の場合の代理店。直販なら null */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * 販売チャネル判定。
     */
    public function isDirectSales(): bool
    {
        return $this->agent_id === null;
    }

    /**
     * この企業に適用される手数料率。
     * commission_rate_override があればそれ、なければ agent.default_commission_rate。
     * 直販（agent_id=null）は 0 を返す。
     */
    public function effectiveCommissionRate(): float
    {
        if ($this->agent_id === null) {
            return 0.0;
        }
        if ($this->commission_rate_override !== null) {
            return (float) $this->commission_rate_override;
        }
        return (float) ($this->agent?->default_commission_rate ?? 0);
    }
}

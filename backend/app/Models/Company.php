<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}

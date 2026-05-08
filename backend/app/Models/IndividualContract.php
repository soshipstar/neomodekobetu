<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 個別契約書 (株式会社ソーシップ × 代理店 × 顧客企業 の3者間契約)
 *
 * 1 代理店 × 1 顧客企業 = 1 契約 (DBユニーク制約あり)。
 * 3者それぞれの署名状態と、最終的な押印済 PDF を保管する。
 */
class IndividualContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'company_id',
        'contract_date',
        'start_date',
        'end_date',
        'terms',
        'monthly_fee',
        'commission_rate',
        'soship_signed',
        'soship_signed_at',
        'agent_signed',
        'agent_signed_at',
        'customer_signed',
        'customer_signed_at',
        'contract_document_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_date'      => 'date:Y-m-d',
            'start_date'         => 'date:Y-m-d',
            'end_date'           => 'date:Y-m-d',
            'monthly_fee'        => 'integer',
            'commission_rate'    => 'decimal:4',
            'soship_signed'      => 'boolean',
            'soship_signed_at'   => 'datetime',
            'agent_signed'       => 'boolean',
            'agent_signed_at'    => 'datetime',
            'customer_signed'    => 'boolean',
            'customer_signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agent, self> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsTo<Company, self> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, self> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 3者全員が署名済みなら true。
     */
    public function isFullySigned(): bool
    {
        return $this->soship_signed && $this->agent_signed && $this->customer_signed;
    }
}

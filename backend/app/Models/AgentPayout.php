<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 代理店への月次手数料支払いレコード。
 * 集計バッチ（毎月1日に前月分を確定）で作成される。
 */
class AgentPayout extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'agent_id',
        'period_start',
        'period_end',
        'due_date',
        'gross_revenue',
        'stripe_fees',
        'net_profit',
        'commission_rate',
        'commission_amount',
        'status',
        'paid_at',
        'transaction_ref',
        'notes',
        'included_invoice_ids',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'gross_revenue' => 'integer',
            'stripe_fees' => 'integer',
            'net_profit' => 'integer',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'integer',
            'included_invoice_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Agent, AgentPayout> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_FINALIZED, self::STATUS_PAID], true);
    }
}

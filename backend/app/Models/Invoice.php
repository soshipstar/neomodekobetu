<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stripe Invoice のローカルキャッシュレコード。
 * 実際の請求情報の正は Stripe 側。Webhook で同期する。
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'number',
        'status',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'subtotal',
        'tax',
        'total',
        'currency',
        'hosted_invoice_url',
        'invoice_pdf',
        'period_start',
        'period_end',
        'due_date',
        'finalized_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'amount_remaining' => 'integer',
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_date' => 'datetime',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, Invoice> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

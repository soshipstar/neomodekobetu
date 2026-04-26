<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stripe Webhook 受信ログ（冪等性確保とリプレイ用）。
 */
class SubscriptionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'company_id',
        'payload',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, SubscriptionEvent> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 月次工賃台帳ヘッダー (事業所 × 年月)。
 */
class WagePeriod extends Model
{
    use HasFactory;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_PAID      = 'paid';

    protected $fillable = [
        'classroom_id',
        'year_month',
        'status',
        'settlement_date',
        'payment_date',
        'finalized_at',
        'finalized_by',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date:Y-m-d',
            'payment_date'    => 'date:Y-m-d',
            'finalized_at'    => 'datetime',
            'paid_at'         => 'datetime',
        ];
    }

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<User, self> */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /** @return HasMany<WageRecord> */
    public function records(): HasMany
    {
        return $this->hasMany(WageRecord::class);
    }
}

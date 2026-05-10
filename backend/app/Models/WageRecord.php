<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 月次工賃台帳明細 (利用者ごとの工賃明細)。
 */
class WageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'wage_period_id',
        'student_id',
        'attendance_days',
        'total_work_minutes',
        'wage_eligible_hours',
        'calculation_type',
        'hourly_rate',
        'piece_rate_amount',
        'base_wage',
        'overtime_minutes',
        'overtime_wage',
        'bonus',
        'deductions',
        'net_wage',
        'notes',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'wage_eligible_hours' => 'decimal:2',
            'hourly_rate'         => 'decimal:2',
            'piece_rate_amount'   => 'decimal:2',
            'base_wage'           => 'decimal:2',
            'overtime_wage'       => 'decimal:2',
            'bonus'               => 'decimal:2',
            'deductions'          => 'decimal:2',
            'net_wage'            => 'decimal:2',
            'calculated_at'       => 'datetime',
        ];
    }

    /** @return BelongsTo<WagePeriod, self> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(WagePeriod::class, 'wage_period_id');
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

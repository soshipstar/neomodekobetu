<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringDetail extends Model
{
    protected $fillable = [
        'monitoring_id',
        'domain',
        'achievement_level',
        'comment',
        'next_action',
        'sort_order',
        'plan_detail_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<MonitoringRecord, self> */
    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(MonitoringRecord::class, 'monitoring_id');
    }

    /** @return BelongsTo<SupportPlanDetail, self> */
    public function planDetail(): BelongsTo
    {
        return $this->belongsTo(SupportPlanDetail::class, 'plan_detail_id');
    }
}

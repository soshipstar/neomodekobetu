<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 個別支援会議 議事録。各個別支援計画(原案)に紐づく。
 * 原案を保護者に提示する前に行う会議の記録 (会議日・出席者・協議内容)。
 */
class SupportPlanMeeting extends Model
{
    protected $fillable = [
        'plan_id',
        'meeting_date',
        'attendees',
        'discussion',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<IndividualSupportPlan, self> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(IndividualSupportPlan::class, 'plan_id');
    }

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

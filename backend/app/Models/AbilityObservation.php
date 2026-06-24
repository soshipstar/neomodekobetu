<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価: 観察記録(T_観察記録)。日々の活動記録から1問ずつ貯める客観評価の素。
 */
class AbilityObservation extends Model
{
    protected $table = 'ability_observations';

    protected $fillable = [
        'classroom_id', 'student_id', 'daily_record_id', 'item_id', 'axis_id',
        'support_code', 'result', 'degree', 'is_new_scene', 'behavior', 'observed_date', 'recorded_by',
    ];

    protected $casts = [
        'is_new_scene' => 'boolean',
        'degree' => 'integer',
        'observed_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalItem::class, 'item_id', 'item_id');
    }

    public function axis(): BelongsTo
    {
        return $this->belongsTo(AbilityEvalAxis::class, 'axis_id', 'axis_id');
    }

    public function supportCode(): BelongsTo
    {
        return $this->belongsTo(AbilitySupportCode::class, 'support_code', 'code');
    }

    public function dailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class);
    }
}

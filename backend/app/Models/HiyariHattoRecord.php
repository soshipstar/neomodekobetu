<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiyariHattoRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'student_id',
        'reporter_id',
        'confirmed_by_id',
        'occurred_at',
        'location',
        'activity_before',
        'student_condition',
        'situation',
        'severity',
        'category',
        'cause_environmental',
        'cause_human',
        'cause_other',
        'immediate_response',
        'guardian_notified',
        'guardian_notified_at',
        'guardian_notification_content',
        'medical_treatment',
        'medical_detail',
        'injury_description',
        'prevention_measures',
        'environment_improvements',
        'staff_sharing_notes',
        'source_daily_record_id',
        'source_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime:Y-m-d H:i:s',
            'guardian_notified_at' => 'datetime:Y-m-d H:i:s',
            'guardian_notified' => 'boolean',
            'medical_treatment' => 'boolean',
        ];
    }

    public const SEVERITIES = [
        'low' => '軽度 (ヒヤリ)',
        'medium' => '中度 (応急処置あり)',
        'high' => '重度 (医療機関受診)',
    ];

    public const CATEGORIES = [
        'fall' => '転倒・転落',
        'collision' => '衝突・接触',
        'choking' => '誤嚥・窒息',
        'ingestion' => '誤食・異物摂取',
        'allergy' => 'アレルギー反応',
        'missing' => '行方不明・離設',
        'conflict' => '児童間トラブル',
        'self_harm' => '自傷行為',
        'vehicle' => '送迎・車両関連',
        'medication' => '投薬関連',
        'other' => 'その他',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    public function sourceDailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class, 'source_daily_record_id');
    }
}

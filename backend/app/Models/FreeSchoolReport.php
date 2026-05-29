<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * フリースクール利用者の活動日 1 日ぶんの報告書。
 * 4 セクション (activity_summary / support_consideration / child_observation /
 *  evaluation_and_next) を AI 生成 → スタッフが編集 → PDF 出力する。
 */
class FreeSchoolReport extends Model
{
    protected $fillable = [
        'classroom_id', 'free_school_user_id', 'student_id', 'daily_record_id',
        'report_date', 'title',
        'activity_summary', 'support_consideration', 'child_observation', 'evaluation_and_next',
        'generated_at', 'generated_by_ai',
        'edited_at', 'edited_by', 'status',
    ];

    protected $casts = [
        'report_date'     => 'date:Y-m-d',
        'generated_at'    => 'datetime',
        'generated_by_ai' => 'boolean',
        'edited_at'       => 'datetime',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function freeSchoolUser(): BelongsTo
    {
        return $this->belongsTo(FreeSchoolUser::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function dailyRecord(): BelongsTo
    {
        return $this->belongsTo(DailyRecord::class);
    }
}

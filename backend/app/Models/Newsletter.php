<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Newsletter extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'year',
        'month',
        'title',
        'greeting',
        'event_calendar',
        'event_details',
        'weekly_reports',
        'event_results',
        'requests',
        'others',
        'status',
        'published_at',
        'created_by',
        'weekly_intro',
        'elementary_report',
        'junior_report',
        'report_start_date',
        'report_end_date',
        'schedule_start_date',
        'schedule_end_date',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'published_at' => 'datetime',
            'report_start_date' => 'date',
            'report_end_date' => 'date',
            'schedule_start_date' => 'date',
            'schedule_end_date' => 'date',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

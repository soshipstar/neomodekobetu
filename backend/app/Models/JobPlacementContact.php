<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPlacementContact extends Model
{
    protected $fillable = [
        'job_placement_id', 'contact_date', 'contact_type', 'contact_with',
        'content', 'issues_raised', 'actions_taken', 'satisfaction_score',
        'attendance_rate', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contact_date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<JobPlacement, self> */
    public function placement(): BelongsTo { return $this->belongsTo(JobPlacement::class, 'job_placement_id'); }
}

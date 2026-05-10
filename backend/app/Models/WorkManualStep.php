<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkManualStep extends Model
{
    protected $fillable = [
        'work_manual_id', 'sort_order', 'title', 'description',
        'image_path', 'video_path', 'caution', 'checkpoint',
    ];

    /** @return BelongsTo<WorkManual, self> */
    public function manual(): BelongsTo { return $this->belongsTo(WorkManual::class, 'work_manual_id'); }
}

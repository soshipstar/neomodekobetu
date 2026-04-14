<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BugReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'page_url',
        'description',
        'console_log',
        'screenshot_path',
        'status',
        'priority',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BugReportReply::class)->orderBy('created_at');
    }
}

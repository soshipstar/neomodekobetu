<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    protected $fillable = [
        'rule', 'user_id', 'user_name', 'user_type', 'count', 'title', 'body',
        'detected_hour', 'is_resolved', 'resolved_note', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'detected_hour' => 'datetime',
        'is_resolved'   => 'boolean',
        'resolved_at'   => 'datetime',
        'count'         => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAccessLog extends Model
{
    public $timestamps = false; // created_at のみ。updated_at は不要

    protected $fillable = [
        'user_id', 'user_type', 'method', 'path', 'status_code',
        'duration_ms', 'ip_address', 'user_agent', 'response_bytes',
        'created_at',
    ];

    protected $casts = [
        'created_at'    => 'datetime',
        'duration_ms'   => 'integer',
        'response_bytes'=> 'integer',
        'status_code'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

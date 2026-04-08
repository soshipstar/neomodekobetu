<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'level',
        'message',
        'exception_class',
        'file',
        'line',
        'trace',
        'url',
        'method',
        'user_id',
        'ip_address',
        'user_agent',
        'request_data',
        'is_resolved',
        'resolved_note',
    ];

    protected function casts(): array
    {
        return [
            'trace'        => 'array',
            'request_data' => 'array',
            'created_at'   => 'datetime',
            'is_resolved'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

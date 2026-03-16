<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'classroom_id',
        'display_settings',
        'calendar_format',
        'ai_instructions',
        'custom_sections',
        'default_requests',
        'default_others',
    ];

    protected function casts(): array
    {
        return [
            'display_settings' => 'array',
            'ai_instructions' => 'array',
            'custom_sections' => 'array',
            'updated_at' => 'datetime',
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
}

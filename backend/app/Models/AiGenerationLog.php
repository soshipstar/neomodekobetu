<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'generation_type',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'input_data',
        'output_data',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'input_data' => 'array',
            'output_data' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

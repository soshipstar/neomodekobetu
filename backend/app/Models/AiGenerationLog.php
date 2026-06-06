<?php

namespace App\Models;

use App\Models\Concerns\HashChainable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    use HashChainable;

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
        // AISI R9 (2026-05-17): ハッシュチェーン
        'row_hash',
        'prev_row_hash',
    ];

    public array $hashFields = [
        'user_id', 'generation_type', 'model',
        'prompt_tokens', 'completion_tokens',
        'input_data', 'output_data', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'input_data' => 'array',
            'output_data' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
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

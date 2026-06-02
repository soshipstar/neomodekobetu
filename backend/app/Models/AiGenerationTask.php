<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 非同期 AI 生成タスク。
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $type
 * @property string $status  pending|processing|completed|failed
 * @property array $input
 * @property array|null $result
 * @property string|null $error
 */
class AiGenerationTask extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'input',
        'result',
        'error',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input'       => 'array',
            'result'      => 'array',
            'duration_ms' => 'integer',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

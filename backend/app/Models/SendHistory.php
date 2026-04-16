<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendHistory extends Model
{
    public $timestamps = false;

    protected $table = 'send_history';

    protected $fillable = [
        'integrated_note_id',
        'guardian_id',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime:Y-m-d H:i:s',
            'read_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<IntegratedNote, self> */
    public function integratedNote(): BelongsTo
    {
        return $this->belongsTo(IntegratedNote::class);
    }

    /** @return BelongsTo<User, self> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }
}

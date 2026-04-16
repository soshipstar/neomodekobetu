<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected $table = 'announcement_reads';

    protected $fillable = [
        'announcement_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /** @return BelongsTo<Announcement, self> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomCapacity extends Model
{
    public $timestamps = false;

    protected $table = 'classroom_capacity';

    protected $fillable = [
        'classroom_id',
        'day_of_week',
        'max_capacity',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'max_capacity' => 'integer',
            'is_open' => 'boolean',
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

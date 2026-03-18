<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'event_date',
        'event_name',
        'event_description',
        'target_audience',
        'event_color',
        'staff_comment',
        'guardian_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
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

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<EventRegistration> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }
}

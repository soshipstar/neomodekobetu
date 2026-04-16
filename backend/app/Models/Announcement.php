<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'title',
        'content',
        'priority',
        'target_type',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime:Y-m-d H:i:s',
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

    /** @return BelongsToMany<Student> */
    public function targetStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'announcement_targets');
    }

    /** @return HasMany<AnnouncementRead> */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkManual extends Model
{
    protected $fillable = [
        'classroom_id', 'title', 'category', 'summary', 'difficulty',
        'estimated_minutes', 'student_id', 'is_published', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }

    /** @return HasMany<WorkManualStep> */
    public function steps(): HasMany { return $this->hasMany(WorkManualStep::class)->orderBy('sort_order'); }
}

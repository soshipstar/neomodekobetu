<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalUsage extends Model
{
    protected $table = 'additional_usages';

    protected $fillable = [
        'student_id',
        'usage_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date:Y-m-d',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Classroom, self> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}

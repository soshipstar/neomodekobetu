<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityEvaluation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'classroom_id',
        'guardian_id',
        'student_id',
        'evaluation_year',
        'responses',
        'comments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_year' => 'integer',
            'responses' => 'array',
            'submitted_at' => 'datetime:Y-m-d H:i:s',
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
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

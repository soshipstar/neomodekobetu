<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFaceSheet extends Model
{
    protected $fillable = [
        'student_id',
        'daily_life',
        'physical',
        'profile',
        'considerations',
        'memo',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'daily_life' => 'array',
            'physical' => 'array',
            'profile' => 'array',
            'considerations' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

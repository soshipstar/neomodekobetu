<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * フリースクール利用者として登録された児童。
 * (classroom_id, student_id) でユニーク。
 */
class FreeSchoolUser extends Model
{
    protected $fillable = [
        'classroom_id', 'student_id', 'registered_at', 'notes', 'registered_by', 'is_active',
    ];

    protected $casts = [
        'registered_at' => 'date:Y-m-d',
        'is_active'     => 'boolean',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(FreeSchoolReport::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}

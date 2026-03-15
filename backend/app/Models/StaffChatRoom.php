<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffChatRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'room_type',
        'room_name',
        'created_by',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<StaffChatMember> */
    public function members(): HasMany
    {
        return $this->hasMany(StaffChatMember::class, 'room_id');
    }

    /** @return HasMany<StaffChatMessage> */
    public function messages(): HasMany
    {
        return $this->hasMany(StaffChatMessage::class, 'room_id');
    }

    /** @return HasMany<StaffChatRead> */
    public function reads(): HasMany
    {
        return $this->hasMany(StaffChatRead::class, 'room_id');
    }
}

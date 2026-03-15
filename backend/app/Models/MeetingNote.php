<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meeting notes are stored as meeting_notes column on meeting_requests table.
 * This model is provided for cases where meeting notes are managed separately.
 */
class MeetingNote extends Model
{
    protected $fillable = [
        'meeting_request_id',
        'content',
        'created_by',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return BelongsTo<MeetingRequest, self> */
    public function meetingRequest(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class);
    }
}

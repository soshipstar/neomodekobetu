<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI学習基盤 同意基盤: 同意記録(append-only。撤回も新規行で積む)。
 */
class ConsentRecord extends Model
{
    const UPDATED_AT = null; // append-only

    protected $fillable = [
        'consent_definition_id', 'consent_key', 'subject_type', 'subject_id', 'company_id',
        'state', 'version', 'granted_by_user_id', 'granted_by_role', 'acquisition_method',
        'evidence_ref', 'acquired_at', 'effective_from', 'note', 'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'acquired_at' => 'datetime',
        'effective_from' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ConsentDefinition::class, 'consent_definition_id');
    }
}

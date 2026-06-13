<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI学習基盤 同意基盤: 同意定義(目的×版×文面)。
 */
class ConsentDefinition extends Model
{
    protected $fillable = [
        'consent_key', 'subject_type', 'title', 'description', 'version', 'policy_url', 'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
    ];
}

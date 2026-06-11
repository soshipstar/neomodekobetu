<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 能力評価マスタ: 才能観察課題(才能サインの活動内チェック方法)。
 */
class AbilityTalentObservationTask extends Model
{
    protected $table = 'ability_talent_observation_tasks';

    protected $primaryKey = 'sign_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['sign_id', 'strength', 'method', 'notes'];

    public function sign(): BelongsTo
    {
        return $this->belongsTo(AbilityTalentSign::class, 'sign_id', 'sign_id');
    }
}

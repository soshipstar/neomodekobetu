<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 能力評価マスタ: 才能サイン(TAL-01〜)。特性を生かした強みの兆候。
 */
class AbilityTalentSign extends Model
{
    protected $table = 'ability_talent_signs';

    protected $primaryKey = 'sign_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['sign_id', 'strength', 'sign', 'grow_activities', 'careers', 'related_item_id'];

    public function observationTask(): HasOne
    {
        return $this->hasOne(AbilityTalentObservationTask::class, 'sign_id', 'sign_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(AbilityTalentCriterion::class, 'sign_id', 'sign_id');
    }
}

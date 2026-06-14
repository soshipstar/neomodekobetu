<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 施設記録基準(E1): 施設独自の記録基準(明示定義)。法人ごと1件・版管理。
 * sections=構造化(文体/必須観点/用語/避ける表現/良い例・悪い例)。guidance_text=生成注入用。
 */
class FacilityWritingStandard extends Model
{
    protected $fillable = [
        'company_id', 'status', 'version', 'sections', 'guidance_text', 'updated_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'version' => 'integer',
    ];
}

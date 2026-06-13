<?php

namespace Database\Seeders;

use App\Models\AiEditReasonCategory;
use Illuminate\Database\Seeder;

/**
 * AI学習基盤: 修正理由の固定カテゴリ(企画書 §11)。
 * 全社共通(company_id=NULL, is_seeded=true)。動的カテゴリは別途昇格で追加される。
 * 冪等(updateOrCreate)。
 */
class AiEditReasonCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'too_abstract', 'label_ja' => '抽象的すぎる', 'description' => '具体性に欠け、本人像が見えない'],
            ['code' => 'too_verbose', 'label_ja' => '冗長・くどい', 'description' => '繰り返しや言い回しが多く長い'],
            ['code' => 'factual_error', 'label_ja' => '事実誤り・創作', 'description' => '記録にない内容や誤った事実が含まれる'],
            ['code' => 'tone_mismatch', 'label_ja' => '文体・敬体不一致', 'description' => '敬体/常体や口調が様式に合わない'],
            ['code' => 'terminology', 'label_ja' => '用語・言い回し', 'description' => '専門用語や言い回しが施設の慣用と異なる'],
            ['code' => 'missing_info', 'label_ja' => '記載漏れ・不足', 'description' => '必要な観点や事実が抜けている'],
            ['code' => 'redundant_info', 'label_ja' => '不要・蛇足', 'description' => '不要な情報が含まれている'],
            ['code' => 'privacy_concern', 'label_ja' => 'プライバシー配慮', 'description' => '配慮すべき個人情報の扱いが不適切'],
            ['code' => 'inappropriate', 'label_ja' => '不適切・配慮不足', 'description' => '表現が不適切、または当事者への配慮を欠く'],
            ['code' => 'format_structure', 'label_ja' => '体裁・構成', 'description' => '段落・順序・書式など構成の問題'],
            ['code' => 'personalization', 'label_ja' => '本人像に合わない', 'description' => '当該児童の実態・特性に合っていない'],
        ];

        $sort = 10;
        foreach ($categories as $c) {
            AiEditReasonCategory::updateOrCreate(
                ['company_id' => null, 'code' => $c['code']],
                $c + [
                    'company_id' => null,
                    'is_seeded' => true,
                    'status' => 'active',
                    'sort_order' => $sort,
                    'usage_count' => 0,
                ],
            );
            $sort += 10;
        }
    }
}

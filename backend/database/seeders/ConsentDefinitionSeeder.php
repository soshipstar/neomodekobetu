<?php

namespace Database\Seeders;

use App\Models\ConsentDefinition;
use Illuminate\Database\Seeder;

/**
 * AI学習基盤 同意基盤: 同意定義の初期投入(企画書 §12)。
 * 目的×版で一意。冪等(updateOrCreate)。
 */
class ConsentDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'consent_key' => 'service_generation',
                'subject_type' => 'student',
                'title' => 'サービス提供のためのAI生成利用',
                'description' => '個別支援計画・モニタリング等の下書きをAIが生成するために、当該児童の記録を利用します。生成結果は職員が確認・修正のうえ確定します。',
            ],
            [
                'consent_key' => 'improvement_aggregate',
                'subject_type' => 'company',
                'title' => '品質改善のための統計利用(施設)',
                'description' => '生成と修正の傾向を施設単位で統計集計し、文章品質と支援案の改善に役立てます。個人を特定しない集計のみに利用します。',
            ],
            [
                'consent_key' => 'model_learning',
                'subject_type' => 'student',
                'title' => 'モデル学習のための利用',
                'description' => '当該児童の記録と職員の修正内容を、文章生成・支援案提案の精度向上(学習)に利用します。利用は施設の統計利用同意があり、かつ本同意がある場合に限ります。',
            ],
            [
                'consent_key' => 'local_ai',
                'subject_type' => 'student',
                'title' => 'ローカルAI学習のための利用(将来)',
                'description' => '将来導入する施設内ローカルAIの学習に当該児童の記録を利用します。外部送信を伴わない学習を想定します。',
            ],
        ];

        foreach ($definitions as $d) {
            ConsentDefinition::updateOrCreate(
                ['consent_key' => $d['consent_key'], 'version' => 1],
                $d + ['version' => 1, 'is_active' => true],
            );
        }
    }
}

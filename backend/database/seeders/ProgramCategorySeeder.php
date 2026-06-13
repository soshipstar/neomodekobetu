<?php

namespace Database\Seeders;

use App\Models\ProgramCategory;
use Illuminate\Database\Seeder;

/**
 * AI学習基盤 S4a: 実施プログラム分類の初期語彙(5領域 × プログラム種別)。
 * 全社共通(company_id=NULL, is_seeded=true)。冪等(updateOrCreate)。
 * aliases は自動分類(ルール照合)用のキーワード。法人独自カテゴリは別途追加・昇格。
 */
class ProgramCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // 健康・生活
            ['domain' => 'health_life', 'code' => 'daily_living', 'label_ja' => '生活習慣・身辺自立', 'aliases' => ['着替え', '排泄', 'トイレ', '手洗い', '片付け', '身辺', '自立', '歯磨き', '清潔', '着脱']],
            ['domain' => 'health_life', 'code' => 'meal', 'label_ja' => '食事・調理', 'aliases' => ['食事', 'おやつ', 'クッキング', '調理', '食育', '料理', 'おやつ作り']],
            ['domain' => 'health_life', 'code' => 'health', 'label_ja' => '健康・衛生', 'aliases' => ['休息', '休憩', '体調', '健康', '衛生', 'うがい']],
            // 運動・感覚
            ['domain' => 'motor_sensory', 'code' => 'gross_motor', 'label_ja' => '粗大運動', 'aliases' => ['運動', '体操', '公園', '散歩', '鬼ごっこ', '粗大', 'ダンス', 'サーキット', 'ボール']],
            ['domain' => 'motor_sensory', 'code' => 'fine_motor', 'label_ja' => '微細運動', 'aliases' => ['制作', '工作', '書字', 'はさみ', 'ぬり絵', '折り紙', '微細', 'ビーズ', 'お絵かき']],
            ['domain' => 'motor_sensory', 'code' => 'sensory', 'label_ja' => '感覚遊び', 'aliases' => ['感覚', '水遊び', '粘土', '砂', '感触', 'スライム']],
            // 認知・行動
            ['domain' => 'cognitive_behavior', 'code' => 'learning', 'label_ja' => '学習支援', 'aliases' => ['宿題', '学習', '読み書き', '計算', 'プリント', 'ドリル', '勉強']],
            ['domain' => 'cognitive_behavior', 'code' => 'cognition_play', 'label_ja' => '認知課題遊び', 'aliases' => ['パズル', 'ルール', 'プログラミング', 'ボードゲーム', 'カードゲーム', '迷路']],
            ['domain' => 'cognitive_behavior', 'code' => 'behavior', 'label_ja' => '行動・自己調整', 'aliases' => ['順番', '切り替え', '行動', '自己調整', 'がまん']],
            // 言語・コミュニケーション
            ['domain' => 'language_communication', 'code' => 'language', 'label_ja' => '言語・発語', 'aliases' => ['言語', '発語', '絵カード', 'ことば', '発音', '言葉']],
            ['domain' => 'language_communication', 'code' => 'expression', 'label_ja' => '発表・表現', 'aliases' => ['音楽', '劇', '発表', '歌', '楽器', '表現', 'リトミック']],
            // 人間関係・社会性
            ['domain' => 'social_relations', 'code' => 'group_play', 'label_ja' => '集団遊び・協同活動', 'aliases' => ['集団', '協同', 'ゲーム', '共同制作', 'グループ', 'みんなで']],
            ['domain' => 'social_relations', 'code' => 'sst', 'label_ja' => 'SST(ソーシャルスキル)', 'aliases' => ['SST', 'ソーシャルスキル', '社会性', 'ロールプレイ', 'あいさつ']],
            ['domain' => 'social_relations', 'code' => 'social_experience', 'label_ja' => '社会体験・外出', 'aliases' => ['外出', '買い物', '社会体験', '公共', 'お出かけ', '遠足', '電車']],
            // 横断
            ['domain' => null, 'code' => 'event', 'label_ja' => '行事・季節イベント', 'aliases' => ['行事', 'イベント', '誕生日', '季節', 'お楽しみ', 'クリスマス', 'ハロウィン']],
            ['domain' => null, 'code' => 'other', 'label_ja' => 'その他', 'aliases' => []],
        ];

        $sort = 10;
        foreach ($categories as $c) {
            ProgramCategory::updateOrCreate(
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

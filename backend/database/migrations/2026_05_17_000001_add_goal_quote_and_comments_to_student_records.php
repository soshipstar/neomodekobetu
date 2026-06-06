<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_records に「領域別の目標引用フラグ + 目標スナップショット」を保存する
 * jsonb 列、および 短期/長期目標に対するコメント列を追加する。
 *
 * 背景 (kiduri2026 と仕様統一 — 2026_05_14_000002 / 000003 相当):
 *
 * 個別支援計画の目標は support_plan_details (plan_id, domain, sub_category, goal)
 * に領域別で格納されている。連絡帳の生徒記録から「この気になったこと (領域 X)
 * について、support plan の domain X 目標を引用する」というフラグを保持したい。
 *
 * - domain_goal_quotes (jsonb)
 *   形式: {
 *     "health_life":         { "quoted": true,  "goal_snapshot": "集団指示を最後まで…" },
 *     "motor_sensory":       { "quoted": false, "goal_snapshot": null },
 *     "cognitive_behavior":  { "quoted": true,  "goal_snapshot": "…" },
 *     "language_communication": { ... },
 *     "social_relations":    { ... }
 *   }
 *   - quoted=true の領域だけ AI プロンプトに引用される
 *   - goal_snapshot は記録時点の目標文言を保持 (後で個別支援計画が更新されても残る)
 *
 * - short_term_goal_comment (text nullable):
 *   個別支援計画の短期目標に対するスタッフのコメント (overall)
 *
 * - long_term_goal_comment (text nullable):
 *   個別支援計画の長期目標に対するスタッフのコメント (overall)
 *
 * AI 統合プロンプト (RenrakuchoController::generateIntegrated) でも値があれば
 * 「【個別支援計画の短期/長期目標に関する所感】」「【個別支援計画から引用する目標】」
 * として組み込み、文章生成に活用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->jsonb('domain_goal_quotes')->nullable()->after('notes')
                ->comment('領域別目標引用設定 { domain_key: { quoted, goal_snapshot } }');
            $table->text('short_term_goal_comment')->nullable()->after('domain_goal_quotes')
                ->comment('個別支援計画の短期目標に対するコメント');
            $table->text('long_term_goal_comment')->nullable()->after('short_term_goal_comment')
                ->comment('個別支援計画の長期目標に対するコメント');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn([
                'domain_goal_quotes',
                'short_term_goal_comment',
                'long_term_goal_comment',
            ]);
        });
    }
};

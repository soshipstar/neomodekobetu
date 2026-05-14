<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * student_records に「領域別の目標引用フラグ + 目標スナップショット」を保存する
 * JSON 列を追加する。
 *
 * 背景: 個別支援計画の目標は support_plan_details (plan_id, domain, goal) に
 * 領域別で格納されている。連絡帳の生徒記録から「この気になったこと (領域 X)
 * について、support plan の domain X 目標を引用する」というフラグを保持したい。
 *
 * 形式 (jsonb):
 * {
 *   "health_life":         { "quoted": true,  "goal_snapshot": "集団指示を最後まで…" },
 *   "motor_sensory":       { "quoted": false, "goal_snapshot": null },
 *   "cognitive_behavior":  { "quoted": true,  "goal_snapshot": "…" },
 *   ...
 * }
 *
 * goal_snapshot を保持する理由:
 * 後で個別支援計画が更新されても、当時の目標文言を残せるようにするため。
 *
 * 同時に、昨日 (2026_05_14_000001) に追加した goal_text / goal_comment は
 * 「単一の目標+コメント」用に作ったが、要件変更により未使用のため drop する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            // 旧列を削除 (昨日追加したが要件変更で使わなくなった)
            if (Schema::hasColumn('student_records', 'goal_text')) {
                $table->dropColumn('goal_text');
            }
            if (Schema::hasColumn('student_records', 'goal_comment')) {
                $table->dropColumn('goal_comment');
            }
        });

        // jsonb 列を追加 (postgres)
        Schema::table('student_records', function (Blueprint $table) {
            $table->jsonb('domain_goal_quotes')->nullable()->after('notes')
                ->comment('領域別目標引用設定 { domain_key: { quoted, goal_snapshot } }');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn('domain_goal_quotes');
            $table->text('goal_text')->nullable();
            $table->text('goal_comment')->nullable();
        });
    }
};

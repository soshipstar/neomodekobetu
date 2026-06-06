<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 個別支援計画の「原案」と「本案」を同一レコード内で分離管理するためのカラム追加。
 *
 * 背景 (要望):
 *   原案 (= draft) と 本案 (= official) を別々に保存できるようにする。
 *   原案画面に「保護者からのコメント (guardian_review_comment)」「会議録 (meetings)」を
 *   同ページで参照しながら、その3つを加味して本案を作成する。
 *   本案は原案からの加筆修正にとどめ、大幅な改定はしない。
 *   本案ページには原案からの変更内容を AI (gpt-5.4) で自動生成した説明文を表示する。
 *   ただし PDF などの印刷物には変更説明文は含めない。
 *
 * 設計判断: 既存の plan レコードを 1 件のまま、原案用フィールド (draft_xxx) と
 * 既存フィールド (= 本案) を併存させる構造を採る。
 *  - draft_life_intention / draft_overall_policy / draft_long_term_goal / draft_short_term_goal
 *    上記が「原案」テキスト。スタッフは原案を保存しても本案は未生成。
 *  - 既存の life_intention / overall_policy / long_term_goal / short_term_goal が
 *    そのまま「本案」テキストとして扱われる (= 公開可能版)。
 *  - 五領域目標 (support_plan_details) は本案にのみ紐付け、原案では持たない
 *    (シンプル化のため。原案では主要 4 項目だけのラフドラフトを許容)。
 *
 * 追加カラム:
 *  - draft_life_intention / draft_overall_policy
 *    / draft_long_term_goal / draft_short_term_goal (text nullable)
 *      原案テキスト本体。
 *  - draft_saved_at      (timestamptz nullable)   原案を保存した時刻
 *  - official_saved_at   (timestamptz nullable)   本案を保存した時刻
 *  - revision_notes      (text nullable)           本案に対する「原案からの変更説明」
 *  - revision_notes_generated_at (timestamptz nullable)
 *      AI による revision_notes 生成時刻 (UI で「再生成」判定に使う)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->text('draft_life_intention')->nullable()->after('life_intention')
                ->comment('原案: 本人/保護者の意向');
            $table->text('draft_overall_policy')->nullable()->after('draft_life_intention')
                ->comment('原案: 総合的な支援方針');
            $table->text('draft_long_term_goal')->nullable()->after('draft_overall_policy')
                ->comment('原案: 長期目標');
            $table->text('draft_short_term_goal')->nullable()->after('draft_long_term_goal')
                ->comment('原案: 短期目標');

            $table->timestampTz('draft_saved_at')->nullable()
                ->comment('原案保存時刻');
            $table->timestampTz('official_saved_at')->nullable()
                ->comment('本案保存時刻');

            $table->text('revision_notes')->nullable()
                ->comment('原案から本案への変更説明 (AI 生成、印刷物には含めない)');
            $table->timestampTz('revision_notes_generated_at')->nullable()
                ->comment('変更説明 AI 生成時刻');
        });
    }

    public function down(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn([
                'draft_life_intention',
                'draft_overall_policy',
                'draft_long_term_goal',
                'draft_short_term_goal',
                'draft_saved_at',
                'official_saved_at',
                'revision_notes',
                'revision_notes_generated_at',
            ]);
        });
    }
};

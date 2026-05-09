<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 連絡帳の strengths データを下流 (モニタリング / 個別支援計画) で活用するための列追加。
 *
 * 1) monitoring_records.strengths_summary jsonb
 *    モニタリング作成時点の対象期間 (前回モニタリング〜今回) における
 *    強み(才能)チェックの集計スナップショットを保存する。
 *    syuro26 の aggregatePeriodTrends 出力と同じ構造を想定。
 *
 * 2) support_plan_details.target_strength*
 *    個別支援計画の各目標 (=領域別 detail) に「強み項目を指標とする」
 *    オプションを持たせる。
 *      target_strength            : 強み項目名 (例: 集中力)
 *      target_strength_baseline   : 計画作成時のスコア (0-10)
 *      target_strength_target     : 計画期間終了時に目指すスコア (0-10)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->jsonb('strengths_summary')->nullable()->after('long_term_goal_achievement')
                ->comment('対象期間の強み(才能)チェック集計スナップショット');
        });

        Schema::table('support_plan_details', function (Blueprint $table) {
            $table->string('target_strength', 100)->nullable()->after('goal')
                ->comment('目標と紐付ける強み項目名');
            $table->unsignedTinyInteger('target_strength_baseline')->nullable()->after('target_strength')
                ->comment('計画作成時の強みスコア (0-10)');
            $table->unsignedTinyInteger('target_strength_target')->nullable()->after('target_strength_baseline')
                ->comment('目指す強みスコア (0-10)');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->dropColumn('strengths_summary');
        });

        Schema::table('support_plan_details', function (Blueprint $table) {
            $table->dropColumn(['target_strength', 'target_strength_baseline', 'target_strength_target']);
        });
    }
};

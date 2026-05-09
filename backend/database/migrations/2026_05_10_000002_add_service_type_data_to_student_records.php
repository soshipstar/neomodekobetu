<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 連絡帳の生徒記録にサービス種別固有データ用の JSON カラムを追加する。
 *
 * 放デイは既存の health_life / motor_sensory / cognitive_behavior /
 * language_communication / social_relations / notes / strengths で完結する
 * ため、新カラムは null のまま運用する。
 *
 * 就労継続支援A/B 用に想定する形式 (旧アプリ syuro26 の userDiaries.activities):
 *   {
 *     "wage_eligible_hours": 4.5,    // 工賃対象時間
 *     "clock_in":  "09:00",          // 出勤時刻 (HH:MM)
 *     "clock_out": "16:00",          // 退勤時刻 (HH:MM)
 *     "work_content": "袋詰め作業"   // 作業内容
 *   }
 *
 * 就労移行支援 用:
 *   {
 *     "practice_content":      "○○商事 接客実習",
 *     "job_search_record":     "ハローワーク訪問・履歴書作成",
 *     "business_manner_score": 4    // 1-5 ビジネスマナー評価
 *   }
 *
 * 放デイ向けの追加項目があればここに追記される (例: pickup_person 等)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->jsonb('service_type_data')->nullable()
                ->after('strengths')
                ->comment('サービス種別固有データ (就労: 工賃時間/出退勤/作業内容、就移: 実習/求職/評価)');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn('service_type_data');
        });
    }
};

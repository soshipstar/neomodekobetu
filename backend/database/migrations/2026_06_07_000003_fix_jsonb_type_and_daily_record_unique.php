<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * jsonb 型の統一 (SCHEMA-06) と daily_records の一意制約の NULL 抜け穴を修正 (SCHEMA-11)。
 *
 * 放デイ業務リスク監査 (P2):
 *
 * SCHEMA-06 activity_support_plans.activity_schedule が json 型で定義されており、
 *   他の jsonb カラム (strengths/service_type_data/domain_goal_quotes 等) と不一致。
 *   json 型は jsonb 演算子 (@> 等) が使えずインデックスも張れない。jsonb に統一する。
 *
 * SCHEMA-11 daily_records の unique(record_date, staff_id, activity_name) が、
 *   PostgreSQL の NULL != NULL の仕様により activity_name が NULL の行で機能せず、
 *   同一日付・同一スタッフで活動名なしの記録が複数作成できてしまっていた。
 *   activity_name を NOT NULL + default '' にして NULL 抜け穴を塞ぐ。
 *
 * PostgreSQL 以外 (sqlite テスト) では型変換系は no-op / 簡易対応。
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // SCHEMA-06: activity_schedule json → jsonb
        if ($isPgsql && Schema::hasColumn('activity_support_plans', 'activity_schedule')) {
            $type = DB::selectOne(
                "SELECT data_type FROM information_schema.columns WHERE table_name = 'activity_support_plans' AND column_name = 'activity_schedule'"
            );
            if ($type && $type->data_type === 'json') {
                DB::statement('ALTER TABLE activity_support_plans ALTER COLUMN activity_schedule TYPE jsonb USING activity_schedule::jsonb');
            }
        }

        // SCHEMA-11: daily_records.activity_name の NULL を '' に統一してから NOT NULL 化
        if (Schema::hasColumn('daily_records', 'activity_name')) {
            DB::table('daily_records')->whereNull('activity_name')->update(['activity_name' => '']);

            if ($isPgsql) {
                DB::statement("ALTER TABLE daily_records ALTER COLUMN activity_name SET DEFAULT ''");
                DB::statement('ALTER TABLE daily_records ALTER COLUMN activity_name SET NOT NULL');
            } else {
                // sqlite 等: change() で NOT NULL + default を試みる
                Schema::table('daily_records', function (Blueprint $table) {
                    $table->string('activity_name', 255)->default('')->nullable(false)->change();
                });
            }
        }
    }

    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql && Schema::hasColumn('activity_support_plans', 'activity_schedule')) {
            DB::statement('ALTER TABLE activity_support_plans ALTER COLUMN activity_schedule TYPE json USING activity_schedule::json');
        }

        if (Schema::hasColumn('daily_records', 'activity_name')) {
            if ($isPgsql) {
                DB::statement('ALTER TABLE daily_records ALTER COLUMN activity_name DROP NOT NULL');
                DB::statement('ALTER TABLE daily_records ALTER COLUMN activity_name DROP DEFAULT');
            } else {
                Schema::table('daily_records', function (Blueprint $table) {
                    $table->string('activity_name', 255)->nullable()->change();
                });
            }
        }
    }
};

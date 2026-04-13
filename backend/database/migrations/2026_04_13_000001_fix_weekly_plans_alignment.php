<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 旧アプリとの整合:
 * 1. achievement カラムを nullable 化（NOT NULL だと未評価時に500エラー）
 * 2. evaluated_by_type / evaluated_by_id 追加（誰が評価したか追跡）
 * 3. created_by_type 追加（student/staff/guardian の区別）
 * 4. weekly_plan_comments に commenter_type 追加
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. achievement カラムを nullable 化
        $achievementCols = [
            'weekly_goal_achievement',
            'shared_goal_achievement',
            'must_do_achievement',
            'should_do_achievement',
            'want_to_do_achievement',
        ];
        foreach ($achievementCols as $col) {
            if (Schema::hasColumn('weekly_plans', $col)) {
                DB::statement("ALTER TABLE weekly_plans ALTER COLUMN {$col} DROP NOT NULL");
                DB::statement("ALTER TABLE weekly_plans ALTER COLUMN {$col} DROP DEFAULT");
            }
        }

        // 2-3. 不足カラム追加
        Schema::table('weekly_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_plans', 'evaluated_by_type')) {
                $table->string('evaluated_by_type', 20)->nullable()->after('evaluated_at');
            }
            if (!Schema::hasColumn('weekly_plans', 'evaluated_by_id')) {
                $table->unsignedBigInteger('evaluated_by_id')->nullable()->after('evaluated_by_type');
            }
            if (!Schema::hasColumn('weekly_plans', 'created_by_type')) {
                $table->string('created_by_type', 20)->nullable()->after('created_by');
            }
        });

        // 4. コメントに commenter_type 追加
        Schema::table('weekly_plan_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_plan_comments', 'commenter_type')) {
                $table->string('commenter_type', 20)->default('staff')->after('user_id');
            }
        });

        // 既存の achievement=0 を NULL に変換（0 は旧アプリで「未評価」の意味）
        DB::statement("UPDATE weekly_plans SET weekly_goal_achievement = NULL WHERE weekly_goal_achievement = 0");
        DB::statement("UPDATE weekly_plans SET shared_goal_achievement = NULL WHERE shared_goal_achievement = 0");
        DB::statement("UPDATE weekly_plans SET must_do_achievement = NULL WHERE must_do_achievement = 0");
        DB::statement("UPDATE weekly_plans SET should_do_achievement = NULL WHERE should_do_achievement = 0");
        DB::statement("UPDATE weekly_plans SET want_to_do_achievement = NULL WHERE want_to_do_achievement = 0");
    }

    public function down(): void
    {
        Schema::table('weekly_plan_comments', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_plan_comments', 'commenter_type')) {
                $table->dropColumn('commenter_type');
            }
        });

        Schema::table('weekly_plans', function (Blueprint $table) {
            $cols = ['evaluated_by_type', 'evaluated_by_id', 'created_by_type'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('weekly_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

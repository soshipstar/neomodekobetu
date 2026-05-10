<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 個別支援計画にサイクル管理項目を追加。
 *
 * 差分カテゴリ: schema
 * 背景: 就労系は 6 ヶ月ごとのモニタリング義務、計画は最低 1 年ごとに見直し。
 *       cycle_number (第N期) と plan_period_start/end (有効期間) を持つことで、
 *       次期モニタリング期日や次期計画作成日を自動算出できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->integer('cycle_number')->nullable()
                ->comment('期番号 (第1期/第2期...)');
            $table->date('plan_period_start')->nullable()
                ->comment('計画有効期間 開始日');
            $table->date('plan_period_end')->nullable()
                ->comment('計画有効期間 終了日');
            $table->date('next_monitoring_due_date')->nullable()
                ->comment('次回モニタリング期日');
            $table->date('next_plan_due_date')->nullable()
                ->comment('次期計画作成期日');
        });
    }

    public function down(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn(['cycle_number', 'plan_period_start', 'plan_period_end', 'next_monitoring_due_date', 'next_plan_due_date']);
        });
    }
};

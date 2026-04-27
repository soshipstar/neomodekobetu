<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 「個別条件書」（individual_terms）を companies に追加する。
 *
 * 標準契約書本文では金額・期日・対象範囲などを「個別条件書に定める」と
 * 抽象化しているが、その具体内容を保持する場所として JSONB を持たせる。
 * Stripe 側の数値（custom_amount / current_period_end など）はそのまま使い、
 * ここには「人が読むための文書情報」を保存する。
 *
 * 想定キー:
 *  monthly_fee, initial_setup_fee, registration_proxy_fee,
 *  service_start_date, contract_term_months, billing_day,
 *  training_visit_count, training_web_count, target_classrooms,
 *  contractor_name, contractor_address, representative,
 *  executed_at, jurisdiction, additional_notes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->jsonb('individual_terms')->nullable()->after('feature_flags');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('individual_terms');
        });
    }
};

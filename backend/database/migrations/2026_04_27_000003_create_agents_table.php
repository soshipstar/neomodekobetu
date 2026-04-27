<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 代理店マスタ。直販以外の販売チャネルを管理する。
 *
 * - default_commission_rate: 既定の手数料率（0〜1の小数。例: 0.20 = 20%）。
 *   各企業ごとに companies.commission_rate_override で上書き可。
 * - bank_info: 振込先銀行情報（行名・支店・口座種別・口座番号・名義）。
 *              JSONで保持。閲覧はマスター管理者のみ。
 * - contract_document_path: 代理店との契約書PDFの保存パス。
 * - contract_terms: 代理店との契約に関する条件文（テキスト、内部メモ）。
 *
 * 手数料の計算基準は「利益（売上 − Stripe手数料）× rate」。
 * 集計と支払いは agent_payouts テーブルで管理する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('code', 50)->nullable()->unique();
            $table->string('contact_name', 200)->nullable();
            $table->string('contact_email', 200)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('address')->nullable();

            // 既定の手数料率（0.0〜1.0）。例: 0.2000 = 20%。
            // companies.commission_rate_override がある場合はそちらを優先。
            $table->decimal('default_commission_rate', 5, 4)->default(0.2);

            // 振込先銀行情報（JSON: bank_name / branch / account_type / account_number / account_holder）
            $table->jsonb('bank_info')->nullable();

            // 契約書PDFと社内メモ
            $table->string('contract_document_path')->nullable();
            $table->text('contract_terms')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};

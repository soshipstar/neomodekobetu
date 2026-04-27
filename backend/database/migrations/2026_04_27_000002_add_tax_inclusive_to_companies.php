<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * カスタム価格を「税込」で扱うか「税別」で扱うかのフラグを追加。
 *
 * - tax_inclusive = true  : custom_amount に入力された金額がすでに税込。
 *                            Stripe の unit_amount もそのまま送信。
 * - tax_inclusive = false : custom_amount は税別金額。
 *                            Stripe の unit_amount は (custom_amount * (1 + tax_rate)) で送信。
 *
 * 既存データは「税込」(true) として扱う（既に Stripe 側に保存されている金額が請求額のため）。
 *
 * tax_rate はアプリの定数として 0.10（消費税10%）を使う想定。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('tax_inclusive')->default(true)->after('is_custom_pricing');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('tax_inclusive');
        });
    }
};

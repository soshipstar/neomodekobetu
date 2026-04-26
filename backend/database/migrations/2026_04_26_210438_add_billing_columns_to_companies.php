<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 企業ごとの契約・請求情報と、マスター管理者による表示制御を持たせる。
 *
 * - subscription_status: Stripe の Subscription ステータスを冗長保存（一覧表示の高速化用キャッシュ）
 * - current_price_id   : 現在の Stripe Price ID（KIDURI / KIDURI LITE / カスタム）
 * - custom_amount      : カスタム価格の月額（円）。標準プランの場合は NULL
 * - is_custom_pricing  : カスタム価格を使っているか
 * - current_period_end : 次回請求日のキャッシュ
 * - cancel_at_period_end: 解約予約フラグ
 * - contract_started_at: 契約開始日（マスター管理者が設定）
 * - contract_notes     : 契約に関する社内メモ（企業管理者には非公開）
 * - contract_document_path: 契約書PDFを個別差し替えする場合のパス
 * - display_settings   : マスター管理者が企業ごとに設定する表示制御 JSON
 * - feature_flags      : 機能ON/OFFフラグ JSON
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('subscription_status', 32)->nullable()->after('trial_ends_at');
            $table->string('current_price_id')->nullable()->after('subscription_status');
            $table->unsignedInteger('custom_amount')->nullable()->after('current_price_id');
            $table->boolean('is_custom_pricing')->default(false)->after('custom_amount');
            $table->timestamp('current_period_end')->nullable()->after('is_custom_pricing');
            $table->boolean('cancel_at_period_end')->default(false)->after('current_period_end');

            $table->timestamp('contract_started_at')->nullable()->after('cancel_at_period_end');
            $table->text('contract_notes')->nullable()->after('contract_started_at');
            $table->string('contract_document_path')->nullable()->after('contract_notes');

            $table->jsonb('display_settings')->nullable()->after('contract_document_path');
            $table->jsonb('feature_flags')->nullable()->after('display_settings');

            $table->index('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['subscription_status']);

            $table->dropColumn([
                'subscription_status',
                'current_price_id',
                'custom_amount',
                'is_custom_pricing',
                'current_period_end',
                'cancel_at_period_end',
                'contract_started_at',
                'contract_notes',
                'contract_document_path',
                'display_settings',
                'feature_flags',
            ]);
        });
    }
};

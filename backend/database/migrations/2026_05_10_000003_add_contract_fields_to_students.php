<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 利用者 (students) に契約期間 / 利用期限 等のサービス種別固有属性を追加する。
 *
 * 旧アプリ syuro26 の ClientFacility (利用者×施設のリッチ pivot) を
 * carebridge では students テーブルの直接列として持たせ、単一所属の
 * 既存設計を崩さずに必要な情報を保持する。
 *
 *  - contract_start_date / contract_end_date :
 *      就労系で契約期間を持つ場合に使用 (放デイは null のまま)。
 *  - usage_limit_date :
 *      就労移行支援 (transition) で 利用開始から 2 年 の利用期限管理に使用。
 *      他種別では未使用。
 *
 * 通所曜日と希望週回数は既存スキーマ (scheduled_monday..sunday booleans /
 * desired_weekly_count int) で既に表現できるためここでは追加しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('contract_start_date')->nullable()->after('support_start_date')
                ->comment('契約開始日 (就労系)');
            $table->date('contract_end_date')->nullable()->after('contract_start_date')
                ->comment('契約終了日 (就労系)');
            $table->date('usage_limit_date')->nullable()->after('contract_end_date')
                ->comment('利用期限 (就労移行は開始から2年)');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'contract_start_date',
                'contract_end_date',
                'usage_limit_date',
            ]);
        });
    }
};

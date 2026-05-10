<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 国保連請求に必要な利用者属性を students に追加。
 *
 * 差分カテゴリ: schema
 * 背景: 障害福祉サービスの請求は受給者証番号 / 支給市町村コード /
 *       上限月額 / 上限管理事業所 を必須とする。
 *       実地指導でこれらが欠けていると請求できない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('beneficiary_number', 20)->nullable()
                ->comment('受給者証番号 (10桁)');
            $table->string('municipality_code', 10)->nullable()
                ->comment('支給市町村コード (6桁)');
            $table->string('disability_category', 30)->nullable()
                ->comment('障害種別 (intellectual | physical | mental | developmental | dual)');
            $table->string('disability_grade', 30)->nullable()
                ->comment('障害支援区分 (区分1-6)');
            $table->integer('monthly_copay_cap')->nullable()
                ->comment('月額負担上限額 (円)');
            $table->string('copay_management_provider', 50)->nullable()
                ->comment('上限管理事業所 (自事業所/別事業所/上限管理対象外)');
            $table->date('certificate_issued_date')->nullable()
                ->comment('受給者証発行日');
            $table->date('certificate_expiry_date')->nullable()
                ->comment('受給者証有効期限');
            $table->integer('monthly_usage_days_cap')->nullable()
                ->comment('月の利用可能日数上限 (例: 23日)');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'beneficiary_number',
                'municipality_code',
                'disability_category',
                'disability_grade',
                'monthly_copay_cap',
                'copay_management_provider',
                'certificate_issued_date',
                'certificate_expiry_date',
                'monthly_usage_days_cap',
            ]);
        });
    }
};

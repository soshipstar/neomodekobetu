<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 国保連請求機能の撤去に伴い、関連カラムを students / classrooms から drop する。
 *
 * 削除対象:
 *  - students: 受給者証番号 / 市町村コード / 障害種別 / 障害支援区分 / 月額負担上限 /
 *             上限管理事業所 / 受給者証発行日 / 有効期限 / 月利用日数上限
 *  - classrooms: WAM 事業所番号 / 都道府県コード / 主使用サービスコード / 単位単価
 *
 * down() で各カラムを復元可能にしているが、データ自体はバックアップから復元する必要がある。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 列が存在するかを確認しながら drop する (idempotent)
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                foreach ([
                    'beneficiary_number',
                    'municipality_code',
                    'disability_category',
                    'disability_grade',
                    'monthly_copay_cap',
                    'copay_management_provider',
                    'certificate_issued_date',
                    'certificate_expiry_date',
                    'monthly_usage_days_cap',
                ] as $col) {
                    if (Schema::hasColumn('students', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('classrooms')) {
            Schema::table('classrooms', function (Blueprint $table) {
                foreach ([
                    'wam_office_code',
                    'prefecture_code',
                    'wam_service_code_default',
                    'wam_unit_price_yen',
                ] as $col) {
                    if (Schema::hasColumn('classrooms', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('beneficiary_number', 20)->nullable();
            $table->string('municipality_code', 10)->nullable();
            $table->string('disability_category', 30)->nullable();
            $table->string('disability_grade', 30)->nullable();
            $table->integer('monthly_copay_cap')->nullable();
            $table->string('copay_management_provider', 50)->nullable();
            $table->date('certificate_issued_date')->nullable();
            $table->date('certificate_expiry_date')->nullable();
            $table->integer('monthly_usage_days_cap')->nullable();
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('wam_office_code', 10)->nullable();
            $table->string('prefecture_code', 2)->nullable();
            $table->string('wam_service_code_default', 6)->nullable();
            $table->integer('wam_unit_price_yen')->nullable();
        });
    }
};

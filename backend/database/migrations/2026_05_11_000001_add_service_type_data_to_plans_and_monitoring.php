<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 個別支援計画 / モニタリングに service_type_data jsonb を追加。
 *
 * 差分カテゴリ: schema
 * 背景: 就労 A/B/移行で固有の項目 (工賃目標、一般就労移行目標、定着支援計画、
 *       訓練段階、求職活動、企業実習評価など) を保存する場所が必要。
 *       student_records に倣って jsonb で柔軟に持たせる。
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        Schema::table('individual_support_plans', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->jsonb('service_type_data')->nullable()
                    ->comment('serviceType 固有データ');
            } else {
                $table->text('service_type_data')->nullable();
            }
        });
        Schema::table('monitoring_records', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->jsonb('service_type_data')->nullable()
                    ->comment('serviceType 固有データ');
            } else {
                $table->text('service_type_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn('service_type_data');
        });
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->dropColumn('service_type_data');
        });
    }
};

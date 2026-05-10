<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 事業所の定員管理。
 *
 * 差分カテゴリ: schema
 * 背景: 就労 A/B/移行 と放デイで指定基準上の定員が異なる
 *      (放デイ最低 10名、就労A最低10名、就労B最低20名、移行最低6名 等)。
 *      利用率の計算に必要。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->integer('capacity')->nullable()->comment('定員 (名)');
            $table->integer('opening_days_per_month')->nullable()->comment('月の開所日数 (営業日)');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'opening_days_per_month']);
        });
    }
};

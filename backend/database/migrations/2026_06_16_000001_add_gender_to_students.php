<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習基盤 S4a: 分析次元「性別」を児童に追加。
 * 入力任意・要配慮個人情報。集計分析のみに使用(プロンプト送信や個票表示には使わない)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // male/female/other/unspecified。NULL=未設定。
            $table->string('gender', 12)->nullable()->after('birth_date')
                ->comment('分析次元(要配慮)。male/female/other/unspecified。集計のみ使用');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};

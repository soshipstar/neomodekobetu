<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 事業所(教室)単位で「SOSHIP Growth OS 連携(SSOログイン)」を使う/使わないを
 * 切り替えるフラグ。既定 false。
 *
 * 実際の判定(verify-login = SOSHIP からのログイン照合)はこの classrooms.soship_enabled を
 * 参照する。マスター管理者が企業単位で有効化し、配下の全事業所へ一括適用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('soship_enabled')->default(false)
                ->comment('SOSHIP Growth OS 連携(SSOログイン)をこの事業所で使うか');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('soship_enabled');
        });
    }
};

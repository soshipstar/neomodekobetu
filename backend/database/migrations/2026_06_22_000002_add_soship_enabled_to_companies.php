<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOSHIP Growth OS 連携(SSOログイン)を「企業単位」で使う/使わないを管理する。
 *
 * マスター管理者が企業管理から切り替え、その企業に紐づくすべての事業所(classrooms)へ
 * 一括適用する。実際の判定(verify-login)は classrooms.soship_enabled を参照する。
 * 既定は false(無効)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('soship_enabled')->default(false)
                ->comment('SOSHIP Growth OS 連携を企業単位で使うか(配下の全事業所へ適用)');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('soship_enabled');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 連絡帳の生徒記録に「強み（才能）チェック」JSON カラムを追加する。
 *
 * 旧アプリ (syuro26) の放デイ user_diaries.activities.strengths に倣い、
 * { "集中力": 0..10, "持続力": 0..10, ... } の連想配列を保存する。
 * キーは UI 側で固定 10 項目を提示する想定。未入力は null または空 JSON。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->jsonb('strengths')->nullable()->comment('強み(才能)チェック {label: score(0-10)}');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn('strengths');
        });
    }
};

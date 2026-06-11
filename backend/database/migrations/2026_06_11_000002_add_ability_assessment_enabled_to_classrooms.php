<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価システム P1: 事業所(教室)単位の利用トグル。
 *
 * 個別支援計画の作成時に評価マスタ(ものさし)を参考データとして使うかを事業所が設定する。
 * ON のとき、その事業所は日々の活動記録入力時に児童ごとの設問が表示される(P2 以降)。
 * 既存の billing_system_enabled と同じく教室単位の boolean 列とする。既定は false。
 *
 * 分類: screen(事業所トグル機能の一部)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('ability_assessment_enabled')->default(false)
                ->comment('能力評価システムをこの事業所で使うか(教室単位トグル)');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('ability_assessment_enabled');
        });
    }
};

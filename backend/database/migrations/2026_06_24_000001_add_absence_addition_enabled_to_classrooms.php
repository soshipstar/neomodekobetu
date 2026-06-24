<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 欠席時対応加算: 事業所(教室)単位の算定可否トグル。
 *
 * 欠席児童に対して欠席時対応加算を「取る/取らない」を事業所が選択する。
 * ON のとき、欠席時対応の記録(加算様式 = absence_response_records)を作成でき、
 * 月次の利用日数一覧で算定回数(上限 月4回/児童)を集計する。OFF の事業所では
 * 入力欄を表示せず、月次カウントも 0 とする。
 * 教室単位の boolean 列。欠席時対応記録は既に稼働中の機能のため、既定は true
 * (現状維持。加算を取らない事業所のみ設定で OFF にする)。
 *
 * 分類: schema(事業所トグル機能の一部)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('absence_addition_enabled')->default(true)
                ->comment('欠席時対応加算をこの事業所で算定するか(教室単位トグル, 既定ON)');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('absence_addition_enabled');
        });
    }
};

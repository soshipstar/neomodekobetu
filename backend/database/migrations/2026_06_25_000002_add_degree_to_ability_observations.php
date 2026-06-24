<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価: 観察記録に「該当度(degree)」を追加(P-D)。
 *
 * 設問に対して「どれくらい該当しているか」を 0〜10 で1つ選ぶ入力。タイミングの合わない
 * 支援コード/結果/行動の代わりに、指導員が答えやすい該当度を主入力にする。
 * degree が入っていれば採点はこれを直接スコアとして優先する(support_code はレガシー併存)。
 *
 * 分類: schema(入力簡素化 P-D の一部)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ability_observations', function (Blueprint $table) {
            $table->unsignedTinyInteger('degree')->nullable()->after('result')
                ->comment('該当度(0-10)。入っていれば採点で直接スコアとして優先する');
        });
    }

    public function down(): void
    {
        Schema::table('ability_observations', function (Blueprint $table) {
            $table->dropColumn('degree');
        });
    }
};

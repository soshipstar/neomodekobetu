<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 事業所(教室)単位で「国保連請求システム(kiduriacount)連携」を使う/使わないを
 * 切り替えるフラグを追加。
 *
 * 要望: kiduriacount(account.kiduri.xyz)の URL が確定したので連携を有効化する。
 *       ただし利用するかどうかは事業所単位でシステム管理者(マスター)が選択でき、
 *       使う事業所だけメニュー(ヘッダの「請求システム」リンク)に表示する。
 *
 * 既定は false(無効)。マスター管理者が事業所ごとに有効化する運用とする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('billing_system_enabled')->default(false)
                ->comment('国保連請求システム(kiduriacount)連携をこの事業所で使うか');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('billing_system_enabled');
        });
    }
};

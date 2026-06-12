<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価 P5: mynameis 連携の結合キーを「メンバーID(member_code)」に対応。
 *
 * mynameis のメンバーID仕様変更により、人手向けの識別子が users.id(数値)から
 * member_code(英字3+数字5, 例 ABC12345, グローバル一意)に変わった。スタッフが
 * mynameis で見えるメンバーIDをそのまま登録できるよう、児童に member_code を保持する。
 * 既存の mynameis_user_id は後方互換のため残す(受信API は member_code 優先、user_id 予備)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('mynameis_member_code', 16)->nullable()->after('mynameis_linked_at')
                ->comment('紐づく mynameis のメンバーID(member_code, 例 ABC12345)');
            $table->index('mynameis_member_code');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('mynameis_member_code');
        });
    }
};

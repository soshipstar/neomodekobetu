<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 生徒・保護者にふりがな列を追加する。
 *
 * 個別支援計画の生徒セレクタや生徒情報一覧の「50音順(あいうえお)」が
 * 正しく並ばないのは、生徒・保護者にふりがなが無く氏名(漢字)で
 * ソートしていたため。ふりがなを保持し、これを基準に並べ替える。
 *
 * 既存レコードには値が無いため nullable。表示・ソートは
 * ふりがなが空のとき漢字氏名へフォールバックする運用とする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_name_kana', 100)->nullable()->after('student_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name_kana', 100)->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('student_name_kana');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('full_name_kana');
        });
    }
};

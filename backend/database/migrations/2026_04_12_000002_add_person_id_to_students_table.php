<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * students.person_id を追加する。
     *
     * 同じ物理的な子どもが複数の教室に在籍するケース（児童複製機能による）で、
     * 「どの Student レコード群が同一人物を指すか」を識別するための uuid。
     *
     * - nullable: 複製を経ていない通常の児童は null（リンクされていない）
     * - 複製時に source/copy の双方に同じ uuid が書き込まれる
     * - 索引を張って同期や一覧取得時の検索を軽くする
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->uuid('person_id')->nullable()->after('guardian_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['person_id']);
            $table->dropColumn('person_id');
        });
    }
};

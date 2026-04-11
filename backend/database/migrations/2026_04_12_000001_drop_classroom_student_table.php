<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * classroom_student ピボットテーブルを撤廃する。
     *
     * 児童の複数教室所属は 1 Student レコード + pivot 方式ではなく、
     * 1 教室につき 1 Student レコードを作成する方式に変更した。
     * （同じ物理的な子どもが複数教室に在籍する場合、guardian_id で
     *   紐づく別 Student レコードを作成する。）
     *
     * backfill により pivot に入っていた行は 1 児童 1 教室を前提とした
     * ものなので、drop による情報損失は無い（primary classroom_id は
     * students テーブルに残っているため）。
     */
    public function up(): void
    {
        Schema::dropIfExists('classroom_student');
    }

    public function down(): void
    {
        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'classroom_id']);
            $table->index('classroom_id');
        });

        // 既存の students.classroom_id から backfill
        DB::statement('
            INSERT INTO classroom_student (student_id, classroom_id, created_at, updated_at)
            SELECT id, classroom_id, NOW(), NOW()
            FROM students
            WHERE classroom_id IS NOT NULL
            ON CONFLICT (student_id, classroom_id) DO NOTHING
        ');
    }
};

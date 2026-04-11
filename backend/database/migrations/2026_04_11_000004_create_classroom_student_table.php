<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 児童と教室の多対多リレーション用中間テーブル。
     *
     * students.classroom_id は「主たる所属教室（現在アクティブな教室）」として残し、
     * classroom_student は「所属している教室の一覧」を表す。
     * これにより 1 名の児童が複数教室で受け入れられるケースに対応する。
     *
     * 後方互換のため、既存の students.classroom_id を中間テーブルに複製して
     * 最低 1 行は必ず存在する状態でスタートする。
     */
    public function up(): void
    {
        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'classroom_id']);
            $table->index('classroom_id');
        });

        // 既存の students.classroom_id を中間テーブルに複製
        DB::statement('
            INSERT INTO classroom_student (student_id, classroom_id, created_at, updated_at)
            SELECT id, classroom_id, NOW(), NOW()
            FROM students
            WHERE classroom_id IS NOT NULL
            ON CONFLICT (student_id, classroom_id) DO NOTHING
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_student');
    }
};

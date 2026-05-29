<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * フリースクール利用者と、その利用者の活動日ごとの報告書テーブルを作成する。
 *
 * 要望:
 *  - サイドメニューの「フリースクール用報告書」から、特定の児童 (= フリースクール
 *    利用者として登録された児童) について、活動日ごとに学校提出用の報告書を
 *    AI 生成 → 編集 → DB 保存 → PDF 出力できるようにする。
 *  - 期間を指定して、表紙 (児童名・期間・発行事業所) 付きの一括 PDF も作れる。
 *
 * テーブル:
 *  free_school_users   : 児童ごとの「フリースクール利用者」登録レコード。
 *                        classroom_id + student_id でユニーク。
 *  free_school_reports : 利用日ごとの報告書本文。AI 生成 + 手動編集。
 *                        free_school_user_id + report_date でユニーク。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_school_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('registered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['classroom_id', 'student_id']);
        });

        Schema::create('free_school_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('free_school_user_id')->constrained('free_school_users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            // 紐づく日次活動レコード (生成時に参照したもの)。
            // 後から daily_record が削除されても報告書本体は残す方針で null OK。
            $table->foreignId('daily_record_id')->nullable()->constrained('daily_records')->nullOnDelete();
            $table->date('report_date');

            // 学校提出用の本文 (AI 生成 → スタッフが編集可)
            $table->string('title', 255)->nullable();
            $table->text('activity_summary')->nullable();        // 【活動概要】
            $table->text('support_consideration')->nullable();   // 【支援内容と五領域への配慮】
            $table->text('child_observation')->nullable();       // 【本人の様子・取り組み】
            $table->text('evaluation_and_next')->nullable();     // 【評価・今後の課題】

            // メタ
            $table->timestamp('generated_at')->nullable();
            $table->boolean('generated_by_ai')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft / finalized

            $table->timestamps();

            $table->unique(['free_school_user_id', 'report_date']);
            $table->index(['classroom_id', 'student_id']);
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_school_reports');
        Schema::dropIfExists('free_school_users');
    }
};

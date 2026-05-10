<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 作業マニュアル機能。
 *
 * 差分カテゴリ: schema
 * 設計:
 *  - work_manuals: 事業所内で共有する手順書 (タイトル/概要/カテゴリ)
 *  - work_manual_steps: ステップ毎 (順序/見出し/本文/メディアパス)
 *  - 利用者個別の "私の手順書" は user_id 付きで分岐できるよう student_id を nullable で持つ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_manuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 50)->nullable()
                ->comment('作業分類 (袋詰め/清掃/データ入力 etc)');
            $table->text('summary')->nullable();
            $table->string('difficulty', 20)->nullable()
                ->comment('initial | intermediate | advanced');
            $table->integer('estimated_minutes')->nullable()->comment('目安所要時間 (分)');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete()
                ->comment('利用者個別の手順書 (合理的配慮): NULL なら共有手順書');
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['classroom_id', 'category']);
            $table->index(['classroom_id', 'student_id']);
        });

        Schema::create('work_manual_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_manual_id')->constrained('work_manuals')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable()->comment('storage/app/public 配下');
            $table->string('video_path', 500)->nullable();
            $table->text('caution')->nullable()->comment('注意事項 / NG 例');
            $table->text('checkpoint')->nullable()->comment('完了チェック条件');
            $table->timestampsTz();
            $table->index(['work_manual_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_manual_steps');
        Schema::dropIfExists('work_manuals');
    }
};

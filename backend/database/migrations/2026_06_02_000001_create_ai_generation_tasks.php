<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 生成タスク (非同期).
 *
 * 背景: 活動支援案などの AI 生成は ~50 秒かかり、外出先のモバイル回線では
 *       応答が返る前に接続が切れて「書き出せない」事象が起きていた。
 *       生成をキューに載せ、結果を DB に保存してフロントがポーリングで
 *       受け取る方式にすることで、回線が切れても結果が消えないようにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // 生成種別 (activity_five_domains / activity_schedule_content など)
            $table->string('type', 64);
            // pending / processing / completed / failed
            $table->string('status', 16)->default('pending');
            // 生成に必要な入力 (プロンプト材料)
            $table->json('input');
            // 生成結果 (JSON)
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_tasks');
    }
};

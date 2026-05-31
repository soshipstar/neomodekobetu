<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 全 /api/* リクエストの監査ログ。
 *
 * 用途:
 *   - 「あるユーザーが急に 1000 件/時の取得をした」「教室外のリソースに 403 を
 *     大量発生させた」などの不正アクセスパターンを後追いで検出。
 *   - 流出・コピー事件が起きたとき、誰が何を見たかを再現するため。
 *
 * 容量対策:
 *   毎日 03:30 に scheduler が 90 日より古いログを物理削除する
 *   (cleanup ジョブを別途 schedule)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_type', 20)->nullable();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->integer('status_code');
            $table->integer('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->integer('response_bytes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_logs');
    }
};

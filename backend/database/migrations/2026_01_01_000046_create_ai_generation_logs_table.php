<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('generation_type', 50)->comment('生成種別');
            $table->string('model', 100)->nullable()->comment('使用AIモデル名');
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->jsonb('input_data')->nullable()->comment('入力データJSON');
            $table->jsonb('output_data')->nullable()->comment('出力データJSON');
            $table->integer('duration_ms')->nullable()->comment('処理時間（ミリ秒）');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('generation_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_logs');
    }
};

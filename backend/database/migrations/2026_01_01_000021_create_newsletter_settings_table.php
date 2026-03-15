<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->unique()->constrained('classrooms')->cascadeOnDelete();
            $table->jsonb('display_settings')->nullable()->comment('表示設定JSON');
            $table->string('calendar_format', 50)->nullable()->comment('カレンダー形式');
            $table->text('ai_instructions')->nullable()->comment('AI生成指示');
            $table->jsonb('custom_sections')->nullable()->comment('カスタムセクションJSON');
            $table->timestampTz('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_settings');
    }
};

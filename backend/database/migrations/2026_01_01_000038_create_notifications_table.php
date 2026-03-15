<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50)->comment('通知種別');
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('link', 500)->nullable()->comment('通知クリック時の遷移先URL');
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->jsonb('data')->nullable()->comment('追加データJSON');
            $table->timestampsTz();

            $table->index('user_id');
            $table->index('is_read');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

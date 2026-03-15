<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->integer('sort_order')->default(1);
            $table->string('routine_name', 100)->comment('ルーティーン名');
            $table->text('routine_content')->nullable()->comment('活動内容');
            $table->string('scheduled_time', 20)->nullable()->comment('実施時間');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['classroom_id', 'sort_order'], 'unique_classroom_order');
            $table->index('classroom_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_routines');
    }
};

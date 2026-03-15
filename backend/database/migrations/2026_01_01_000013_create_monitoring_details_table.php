<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_id')->constrained('monitoring_records')->cascadeOnDelete();
            $table->string('domain', 100)->nullable()->comment('領域');
            $table->string('achievement_level', 50)->nullable()->comment('達成度');
            $table->text('comment')->nullable()->comment('コメント');
            $table->text('next_action')->nullable()->comment('次のアクション');
            $table->integer('sort_order')->default(0)->comment('表示順');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_details');
    }
};

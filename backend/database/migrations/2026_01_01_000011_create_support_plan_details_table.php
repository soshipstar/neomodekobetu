<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_plan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('individual_support_plans')->cascadeOnDelete();
            $table->string('domain', 100)->nullable()->comment('領域（健康・生活、運動・感覚等）');
            $table->text('current_status')->nullable()->comment('現状');
            $table->text('goal')->nullable()->comment('目標');
            $table->text('support_content')->nullable()->comment('支援内容');
            $table->string('achievement_status', 50)->nullable()->comment('達成状況');
            $table->integer('sort_order')->default(0)->comment('表示順');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_plan_details');
    }
};

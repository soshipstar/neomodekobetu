<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->date('week_start_date');
            $table->jsonb('plan_content')->nullable()->comment('計画内容JSON');
            $table->string('status', 30)->default('draft')->comment('draft, published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('classroom_id');
            $table->index('week_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_plans');
    }
};

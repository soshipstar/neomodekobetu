<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_plan_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_id')->constrained('weekly_plans')->cascadeOnDelete();
            $table->string('submission_item', 255);
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestampTz('completed_at')->nullable();
            $table->string('completed_by_type', 20)->nullable()->comment('student, staff');
            $table->unsignedBigInteger('completed_by_id')->nullable();
            $table->timestampsTz();

            $table->index('weekly_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_submissions');
    }
};

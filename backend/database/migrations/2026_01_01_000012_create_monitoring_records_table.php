<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('individual_support_plans')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->date('monitoring_date')->nullable();
            $table->text('overall_comment')->nullable();
            $table->text('short_term_goal_achievement')->nullable();
            $table->text('long_term_goal_achievement')->nullable();
            $table->boolean('is_official')->default(false)->comment('正式版フラグ');
            $table->boolean('guardian_confirmed')->default(false);
            $table->timestampTz('guardian_confirmed_at')->nullable();
            $table->text('staff_signature')->nullable()->comment('Base64エンコードされた職員署名');
            $table->text('guardian_signature')->nullable()->comment('保護者署名');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('plan_id');
            $table->index('classroom_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_records');
    }
};

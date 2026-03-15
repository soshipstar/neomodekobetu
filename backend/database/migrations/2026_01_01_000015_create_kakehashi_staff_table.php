<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kakehashi_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('kakehashi_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->text('student_wish')->nullable()->comment('児童の意思');
            $table->text('short_term_goal')->nullable();
            $table->text('long_term_goal')->nullable();
            $table->text('health_life')->nullable()->comment('健康・生活');
            $table->text('motor_sensory')->nullable()->comment('運動・感覚');
            $table->text('cognitive_behavior')->nullable()->comment('認知・行動');
            $table->text('language_communication')->nullable()->comment('言語・コミュニケーション');
            $table->text('social_relations')->nullable()->comment('人間関係・社会性');
            $table->boolean('is_submitted')->default(false);
            $table->timestampTz('submitted_at')->nullable();
            $table->boolean('guardian_confirmed')->default(false);
            $table->timestampTz('guardian_confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index('period_id');
            $table->index('student_id');
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kakehashi_staff');
    }
};

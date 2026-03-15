<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_record_id')->constrained('daily_records')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('health_life')->nullable()->comment('健康・生活');
            $table->text('motor_sensory')->nullable()->comment('運動・感覚');
            $table->text('cognitive_behavior')->nullable()->comment('認知・行動');
            $table->text('language_communication')->nullable()->comment('言語・コミュニケーション');
            $table->text('social_relations')->nullable()->comment('人間関係・社会性');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestampsTz();

            $table->index('daily_record_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_records');
    }
};

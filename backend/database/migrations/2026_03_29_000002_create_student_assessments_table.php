<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('domain', 50)->comment('5領域キー: health_life, motor_sensory, cognitive_behavior, language_communication, social_relations');
            $table->string('item_key', 50)->comment('項目キー');
            $table->text('current_status')->nullable()->comment('現在の状況');
            $table->text('support_needs')->nullable()->comment('支援の必要性・方針');
            $table->smallInteger('level')->default(3)->comment('評価レベル 1-5');
            $table->text('notes')->nullable()->comment('備考');
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete()->comment('評価者');
            $table->timestampsTz();

            $table->unique(['student_id', 'domain', 'item_key']);
            $table->index(['student_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_assessments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kakehashi_guardian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('kakehashi_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('home_situation')->nullable()->comment('家庭での状況');
            $table->text('concerns')->nullable()->comment('心配事・気になること');
            $table->text('requests')->nullable()->comment('要望');
            $table->boolean('is_submitted')->default(false);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();

            $table->index('period_id');
            $table->index('student_id');
            $table->index('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kakehashi_guardian');
    }
};

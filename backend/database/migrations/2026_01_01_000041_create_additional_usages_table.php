<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('usage_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['student_id', 'usage_date'], 'unique_student_usage_date');
            $table->index('student_id');
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_usages');
    }
};

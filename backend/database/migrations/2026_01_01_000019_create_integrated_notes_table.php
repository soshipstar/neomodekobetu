<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrated_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_record_id')->constrained('daily_records')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('integrated_content')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->timestampTz('sent_at')->nullable();
            $table->boolean('guardian_confirmed')->default(false);
            $table->timestampTz('guardian_confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index('daily_record_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrated_notes');
    }
};

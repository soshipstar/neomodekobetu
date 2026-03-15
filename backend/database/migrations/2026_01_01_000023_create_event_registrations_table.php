<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('registered')->comment('registered, cancelled');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('event_id');
            $table->index('student_id');
            $table->index('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('chat_rooms')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestampTz('completed_at')->nullable();
            $table->text('completed_note')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->integer('attachment_size')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_requests');
    }
};

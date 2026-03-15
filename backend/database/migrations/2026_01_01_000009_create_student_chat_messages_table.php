<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('student_chat_rooms')->cascadeOnDelete();
            $table->string('sender_type', 20)->comment('student, staff');
            $table->unsignedBigInteger('sender_id');
            $table->string('message_type', 50)->default('text')->comment('text, image, file');
            $table->text('message')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->integer('attachment_size')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_read')->default(false);

            $table->index('room_id');
        });

        DB::statement("ALTER TABLE student_chat_messages ADD CONSTRAINT student_chat_messages_sender_type_check CHECK (sender_type IN ('student', 'staff'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('student_chat_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->enum('room_type', ['direct', 'group']);
            $table->string('room_name')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_chat_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('staff_chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('joined_at')->useCurrent();
            $table->unique(['room_id', 'user_id']);
        });

        Schema::create('staff_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('staff_chat_rooms')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->integer('attachment_size')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('staff_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('staff_chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('last_read_message_id')->default(0);
            $table->timestampTz('read_at')->nullable();
            $table->unique(['room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_chat_reads');
        Schema::dropIfExists('staff_chat_messages');
        Schema::dropIfExists('staff_chat_members');
        Schema::dropIfExists('staff_chat_rooms');
    }
};

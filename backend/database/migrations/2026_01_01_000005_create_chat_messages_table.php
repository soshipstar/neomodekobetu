<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_type', 20)->default('staff')->comment('staff, guardian');
            $table->string('message_type', 50)->default('text')->comment('text, image, file, meeting_request');
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->integer('attachment_size')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestampTz('deleted_at')->nullable();
            $table->unsignedBigInteger('meeting_request_id')->nullable()->comment('関連する面談リクエストID');

            $table->index('room_id');
            $table->index('meeting_request_id');
        });

        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_sender_type_check CHECK (sender_type IN ('staff', 'guardian'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};

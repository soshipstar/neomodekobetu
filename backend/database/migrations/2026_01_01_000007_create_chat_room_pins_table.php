<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('pinned_at')->useCurrent();

            $table->unique(['room_id', 'staff_id'], 'unique_pin');
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_pins');
    }
};

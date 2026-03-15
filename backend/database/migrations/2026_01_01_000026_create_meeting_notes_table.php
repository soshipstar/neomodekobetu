<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests')->cascadeOnDelete();
            $table->text('content')->nullable()->comment('面談メモ');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_notes');
    }
};

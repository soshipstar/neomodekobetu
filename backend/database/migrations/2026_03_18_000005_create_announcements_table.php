<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('content');
            $table->string('priority', 20)->default('normal')->comment('normal, important, urgent');
            $table->string('target_type', 20)->default('all')->comment('all, selected');
            $table->boolean('is_published')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->index(['classroom_id', 'is_published', 'published_at']);
        });

        DB::statement("ALTER TABLE announcements ADD CONSTRAINT announcements_priority_check CHECK (priority IN ('normal', 'important', 'urgent'))");
        DB::statement("ALTER TABLE announcements ADD CONSTRAINT announcements_target_type_check CHECK (target_type IN ('all', 'selected'))");

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->unique(['announcement_id', 'student_id']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('read_at')->useCurrent();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
    }
};

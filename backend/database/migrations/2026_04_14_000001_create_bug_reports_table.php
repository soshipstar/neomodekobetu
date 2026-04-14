<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('page_url', 500);
            $table->text('description');
            $table->text('console_log')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status', 20)->default('open'); // open, in_progress, resolved, closed
            $table->string('priority', 10)->default('normal'); // low, normal, high, critical
            $table->timestampsTz();

            $table->index('reporter_id');
            $table->index('status');
        });

        Schema::create('bug_report_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_report_id')->constrained('bug_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('bug_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_report_replies');
        Schema::dropIfExists('bug_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->integer('year');
            $table->integer('month');
            $table->string('title', 255)->nullable();
            $table->text('greeting')->nullable();
            $table->text('event_calendar')->nullable();
            $table->text('event_details')->nullable();
            $table->text('weekly_reports')->nullable();
            $table->text('event_results')->nullable();
            $table->text('requests')->nullable();
            $table->text('others')->nullable();
            $table->date('report_start_date')->nullable();
            $table->date('report_end_date')->nullable();
            $table->date('schedule_start_date')->nullable();
            $table->date('schedule_end_date')->nullable();
            $table->string('status', 20)->default('draft')->comment('draft, published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['year', 'month']);
        });

        DB::statement("ALTER TABLE newsletters ADD CONSTRAINT newsletters_status_check CHECK (status IN ('draft', 'published'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};

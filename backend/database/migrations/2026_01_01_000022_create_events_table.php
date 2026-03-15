<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->string('event_name', 255)->comment('イベント名');
            $table->text('event_description')->nullable()->comment('イベント説明');
            $table->string('target_audience', 30)->default('all')->comment('elementary, junior_high_school, all, guardian, other');
            $table->string('event_color', 7)->default('#28a745')->comment('カレンダー表示色');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->text('staff_comment')->nullable()->comment('スタッフ向けコメント');
            $table->text('guardian_message')->nullable()->comment('保護者・生徒連絡用メッセージ');
            $table->timestampsTz();

            $table->index('event_date');
            $table->index('classroom_id');
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_target_audience_check CHECK (target_audience IN ('elementary', 'junior_high_school', 'all', 'guardian', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

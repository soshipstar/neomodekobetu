<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->date('diary_date');
            $table->text('previous_day_review')->nullable()->comment('前日の振り返り');
            $table->text('daily_communication')->nullable()->comment('本日の伝達事項');
            $table->text('daily_roles')->nullable()->comment('本日の役割分担');
            $table->text('prev_day_children_status')->nullable()->comment('前日の児童の状況');
            $table->text('children_special_notes')->nullable()->comment('児童の特記事項');
            $table->text('other_notes')->nullable()->comment('その他メモ');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['classroom_id', 'diary_date'], 'unique_classroom_date');
            $table->index('diary_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_diaries');
    }
};

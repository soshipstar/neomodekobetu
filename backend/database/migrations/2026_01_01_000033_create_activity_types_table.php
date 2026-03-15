<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('activity_name', 100);
            $table->string('day_type', 20)->default('both')->comment('weekday, holiday, both');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('classroom_id');
        });

        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_day_type_check CHECK (day_type IN ('weekday', 'holiday', 'both'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_types');
    }
};

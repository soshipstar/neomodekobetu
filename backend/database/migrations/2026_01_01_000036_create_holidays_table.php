<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->string('holiday_name', 100)->nullable();
            $table->string('holiday_type', 20)->default('regular')->comment('regular, special');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('holiday_date');
            $table->index('classroom_id');
        });

        DB::statement("ALTER TABLE holidays ADD CONSTRAINT holidays_holiday_type_check CHECK (holiday_type IN ('regular', 'special'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};

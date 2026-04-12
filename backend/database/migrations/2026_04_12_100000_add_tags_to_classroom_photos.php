<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_photos', function (Blueprint $table) {
            $table->string('day_of_week', 20)->nullable()->after('activity_date');
            $table->string('grade_level', 30)->nullable()->after('day_of_week');
            $table->foreignId('activity_tag_id')->nullable()->after('grade_level')
                ->constrained('classroom_tags')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classroom_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_tag_id');
            $table->dropColumn(['day_of_week', 'grade_level']);
        });
    }
};

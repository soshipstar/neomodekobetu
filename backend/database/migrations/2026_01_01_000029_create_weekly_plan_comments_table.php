<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_plan_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('weekly_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('plan_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_comments');
    }
};

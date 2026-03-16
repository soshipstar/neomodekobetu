<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_plans', 'student_id')) {
                $table->foreignId('student_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
                $table->index('student_id');
            }
            // Add missing columns from legacy schema
            if (!Schema::hasColumn('weekly_plans', 'weekly_goal')) {
                $table->text('weekly_goal')->nullable();
                $table->text('shared_goal')->nullable();
                $table->text('must_do')->nullable();
                $table->text('should_do')->nullable();
                $table->text('want_to_do')->nullable();
                $table->tinyInteger('weekly_goal_achievement')->default(0);
                $table->text('weekly_goal_comment')->nullable();
                $table->tinyInteger('shared_goal_achievement')->default(0);
                $table->text('shared_goal_comment')->nullable();
                $table->tinyInteger('must_do_achievement')->default(0);
                $table->text('must_do_comment')->nullable();
                $table->tinyInteger('should_do_achievement')->default(0);
                $table->text('should_do_comment')->nullable();
                $table->tinyInteger('want_to_do_achievement')->default(0);
                $table->text('want_to_do_comment')->nullable();
                $table->jsonb('daily_achievement')->nullable();
                $table->text('overall_comment')->nullable();
                $table->timestampTz('evaluated_at')->nullable();
                $table->jsonb('plan_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropColumn([
                'student_id', 'weekly_goal', 'shared_goal', 'must_do', 'should_do', 'want_to_do',
                'weekly_goal_achievement', 'weekly_goal_comment', 'shared_goal_achievement', 'shared_goal_comment',
                'must_do_achievement', 'must_do_comment', 'should_do_achievement', 'should_do_comment',
                'want_to_do_achievement', 'want_to_do_comment', 'daily_achievement', 'overall_comment',
                'evaluated_at', 'plan_data',
            ]);
        });
    }
};

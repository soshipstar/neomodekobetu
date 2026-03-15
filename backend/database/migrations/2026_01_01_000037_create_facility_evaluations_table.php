<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 簡易評価テーブル（FacilityEvaluationモデル用）
        Schema::create('facility_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->integer('evaluation_year')->comment('評価年度');
            $table->jsonb('responses')->nullable()->comment('回答JSON');
            $table->text('comments')->nullable()->comment('コメント');
            $table->timestampTz('submitted_at')->nullable();

            $table->index('classroom_id');
            $table->index('guardian_id');
            $table->index('evaluation_year');
        });

        // 評価期間テーブル
        Schema::create('facility_evaluation_periods', function (Blueprint $table) {
            $table->id();
            $table->integer('fiscal_year')->comment('年度');
            $table->string('title', 100)->comment('評価期間タイトル');
            $table->string('status', 20)->default('draft')->comment('draft, collecting, aggregating, published');
            $table->date('guardian_deadline')->nullable()->comment('保護者回答期限');
            $table->date('staff_deadline')->nullable()->comment('スタッフ回答期限');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique('fiscal_year');
        });

        DB::statement("ALTER TABLE facility_evaluation_periods ADD CONSTRAINT facility_evaluation_periods_status_check CHECK (status IN ('draft', 'collecting', 'aggregating', 'published'))");

        // 評価質問マスタ
        Schema::create('facility_evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_type', 20)->comment('guardian, staff');
            $table->string('category', 100)->comment('カテゴリ');
            $table->integer('question_number')->comment('質問番号');
            $table->text('question_text')->comment('質問文');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['question_type', 'question_number'], 'unique_question');
        });

        DB::statement("ALTER TABLE facility_evaluation_questions ADD CONSTRAINT facility_evaluation_questions_type_check CHECK (question_type IN ('guardian', 'staff'))");

        // 保護者評価回答
        Schema::create('facility_guardian_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('facility_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->boolean('is_submitted')->default(false);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['period_id', 'guardian_id'], 'unique_guardian_period');
        });

        // 保護者評価回答詳細
        Schema::create('facility_guardian_evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('facility_guardian_evaluations')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('facility_evaluation_questions')->cascadeOnDelete();
            $table->string('answer', 20)->nullable()->comment('yes, neutral, no, unknown');
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->unique(['evaluation_id', 'question_id'], 'unique_guardian_answer');
        });

        DB::statement("ALTER TABLE facility_guardian_evaluation_answers ADD CONSTRAINT facility_guardian_eval_answer_check CHECK (answer IN ('yes', 'neutral', 'no', 'unknown'))");

        // スタッフ自己評価回答
        Schema::create('facility_staff_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('facility_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_submitted')->default(false);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['period_id', 'staff_id'], 'unique_staff_period');
        });

        // スタッフ自己評価回答詳細
        Schema::create('facility_staff_evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('facility_staff_evaluations')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('facility_evaluation_questions')->cascadeOnDelete();
            $table->string('answer', 20)->nullable()->comment('yes, neutral, no, unknown');
            $table->text('comment')->nullable();
            $table->text('improvement_plan')->nullable()->comment('改善計画');
            $table->timestampsTz();

            $table->unique(['evaluation_id', 'question_id'], 'unique_staff_answer');
        });

        DB::statement("ALTER TABLE facility_staff_evaluation_answers ADD CONSTRAINT facility_staff_eval_answer_check CHECK (answer IN ('yes', 'neutral', 'no', 'unknown'))");

        // 集計結果（公表用）
        Schema::create('facility_evaluation_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('facility_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('facility_evaluation_questions')->cascadeOnDelete();
            $table->integer('yes_count')->default(0);
            $table->integer('neutral_count')->default(0);
            $table->integer('no_count')->default(0);
            $table->integer('unknown_count')->default(0);
            $table->decimal('yes_percentage', 5, 2)->default(0);
            $table->text('comment_summary')->nullable()->comment('コメント要約（AI生成）');
            $table->text('facility_comment')->nullable()->comment('事業所コメント');
            $table->timestampsTz();

            $table->unique(['period_id', 'question_id'], 'unique_summary');
        });

        // 自己評価総括表
        Schema::create('facility_self_evaluation_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('facility_evaluation_periods')->cascadeOnDelete();
            $table->string('category', 100)->comment('カテゴリ');
            $table->text('current_status')->nullable()->comment('現状の取組');
            $table->text('issues')->nullable()->comment('課題');
            $table->text('improvement_plan')->nullable()->comment('改善計画');
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['period_id', 'category'], 'unique_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_evaluations');
        Schema::dropIfExists('facility_self_evaluation_summary');
        Schema::dropIfExists('facility_evaluation_summaries');
        Schema::dropIfExists('facility_staff_evaluation_answers');
        Schema::dropIfExists('facility_staff_evaluations');
        Schema::dropIfExists('facility_guardian_evaluation_answers');
        Schema::dropIfExists('facility_guardian_evaluations');
        Schema::dropIfExists('facility_evaluation_questions');
        Schema::dropIfExists('facility_evaluation_periods');
    }
};

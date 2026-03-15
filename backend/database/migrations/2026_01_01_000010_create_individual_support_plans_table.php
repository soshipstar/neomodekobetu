<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_support_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->string('student_name', 100)->nullable();
            $table->date('created_date')->nullable();
            $table->text('life_intention')->nullable()->comment('生活に対する意向');
            $table->text('overall_policy')->nullable()->comment('総合的な援助の方針');
            $table->text('long_term_goal')->nullable();
            $table->text('short_term_goal')->nullable();
            $table->date('consent_date')->nullable()->comment('同意日');
            $table->text('basis_content')->nullable()->comment('根拠内容');
            $table->string('plan_source_period')->nullable()->comment('計画元期間');
            $table->string('start_type')->default('current')->comment('開始種別');
            $table->string('status', 30)->default('draft')->comment('draft, review, approved, archived');
            $table->boolean('is_official')->default(false)->comment('正式版フラグ');
            $table->text('staff_signature')->nullable()->comment('Base64エンコードされた職員署名');
            $table->date('staff_signature_date')->nullable()->comment('職員署名日');
            $table->text('guardian_signature')->nullable()->comment('保護者署名');
            $table->date('guardian_signature_date')->nullable()->comment('保護者署名日');
            $table->text('guardian_review_comment')->nullable()->comment('保護者レビューコメント');
            $table->timestampTz('guardian_reviewed_at')->nullable()->comment('保護者レビュー日時');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('classroom_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_support_plans');
    }
};

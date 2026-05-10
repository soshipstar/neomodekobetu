<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 就労移行支援 3 大機能テーブル:
 *  1. job_applications   - 応募管理 (会社/職種/応募日/結果)
 *  2. company_internships - 企業実習 (実習先/期間/評価)
 *  3. job_placements     - 就職後定着支援 (定着スケジュール / 連絡履歴)
 *
 * 差分カテゴリ: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. 求職応募管理
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('company_name', 255);
            $table->string('industry', 100)->nullable()->comment('業種');
            $table->string('job_title', 255)->nullable()->comment('職種');
            $table->string('employment_type', 50)->nullable()
                ->comment('full_time | part_time | contract | other');
            $table->date('application_date');
            $table->string('source', 50)->nullable()
                ->comment('hello_work | introduction | direct | event | other');
            $table->string('status', 30)->default('applied')
                ->comment('applied | screening | interview_scheduled | interviewed | offered | accepted | rejected | withdrawn');
            $table->date('interview_date')->nullable();
            $table->date('result_date')->nullable();
            $table->text('result_notes')->nullable();
            $table->text('feedback')->nullable()->comment('面接後フィードバック');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['classroom_id', 'application_date']);
            $table->index(['student_id', 'status']);
        });

        // 2. 企業実習
        Schema::create('company_internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('company_name', 255);
            $table->string('contact_person', 100)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('total_days')->nullable()->comment('実習日数');
            $table->string('internship_type', 50)->default('observation')
                ->comment('observation | hands_on | trial_employment');
            $table->text('purpose')->nullable()->comment('実習目的');
            $table->text('plan_content')->nullable()->comment('実習計画');
            $table->text('daily_logs')->nullable()->comment('実習日報のサマリ');
            $table->text('company_evaluation')->nullable()->comment('実習先評価');
            $table->integer('attitude_score')->nullable()->comment('就労意欲評価 1-5');
            $table->integer('skill_score')->nullable()->comment('技能評価 1-5');
            $table->integer('communication_score')->nullable()->comment('対人面評価 1-5');
            $table->text('staff_evaluation')->nullable()->comment('事業所評価');
            $table->string('outcome', 30)->nullable()
                ->comment('led_to_employment | continued_training | suspended | completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['classroom_id', 'start_date']);
            $table->index('student_id');
        });

        // 3. 就職後定着支援 (就職した OB/OG)
        Schema::create('job_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('company_name', 255);
            $table->string('job_title', 255)->nullable();
            $table->date('start_date')->comment('就職開始日');
            $table->date('end_date')->nullable()->comment('離職日');
            $table->string('employment_type', 50)->nullable();
            $table->decimal('monthly_salary', 10, 2)->nullable()->comment('月収 (円)');
            $table->integer('weekly_hours')->nullable()->comment('週労働時間');
            $table->string('status', 30)->default('active')
                ->comment('active | resigned | terminated | transferred');
            $table->text('reasonable_accommodations')->nullable()->comment('合理的配慮の内容');
            $table->date('next_followup_date')->nullable()->comment('次回フォロー期日');
            $table->text('separation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['classroom_id', 'status']);
            $table->index('student_id');
        });

        // 4. 定着支援連絡履歴 (job_placement に紐づく月次/四半期コンタクト)
        Schema::create('job_placement_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_placement_id')->constrained('job_placements')->cascadeOnDelete();
            $table->date('contact_date');
            $table->string('contact_type', 30)->default('visit')
                ->comment('visit | phone | email | meeting | counseling');
            $table->string('contact_with', 100)->nullable()->comment('連絡相手 (本人/上司/家族 等)');
            $table->text('content');
            $table->text('issues_raised')->nullable()->comment('課題・相談内容');
            $table->text('actions_taken')->nullable()->comment('対応内容');
            $table->integer('satisfaction_score')->nullable()->comment('本人満足度 1-5');
            $table->integer('attendance_rate')->nullable()->comment('直近 1 ヶ月出勤率 %');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['job_placement_id', 'contact_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_placement_contacts');
        Schema::dropIfExists('job_placements');
        Schema::dropIfExists('company_internships');
        Schema::dropIfExists('job_applications');
    }
};

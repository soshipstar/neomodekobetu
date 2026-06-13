<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習基盤 S4a: 生成/修正イベントに「記録時点の分析次元」をスナップショット保存する。
 *
 * 学年(コホート)・成長段階は時間で変わるため、集計の履歴正確性を担保するべく
 * イベント記録時点の値を凍結して持つ。作者(user_id/editor_user_id)・施設(company_id/
 * classroom_id)は既存列をそのまま軸に使う。実名は含めない(数値・区分のみ)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generation_events', function (Blueprint $table) {
            $table->string('subj_cohort', 16)->nullable()->comment('preschool/elementary/junior_high/high_school/other');
            $table->string('subj_growth_stage', 4)->nullable()->comment('S1〜S6(AbilityGrowthStage)');
            $table->string('subj_grade_level', 20)->nullable()->comment('記録時点のgrade_level生値');
            $table->string('subj_gender', 12)->nullable();
            $table->index(['company_id', 'document_type', 'subj_cohort'], 'aigen_dim_idx');
        });

        Schema::table('ai_revision_events', function (Blueprint $table) {
            $table->string('subj_cohort', 16)->nullable();
            $table->string('subj_growth_stage', 4)->nullable();
            $table->string('subj_grade_level', 20)->nullable();
            $table->string('subj_gender', 12)->nullable();
            $table->string('support_category', 40)->nullable()->comment('section_key由来(本人支援:健康・生活 等)');
            $table->unsignedBigInteger('program_category_id')->nullable()->comment('連絡帳/活動由来の修正に付与');
            $table->jsonb('dim_meta')->nullable()->comment('traits[]/sup_code 等の拡張次元');

            $table->index(['company_id', 'document_type', 'subj_cohort'], 'airev_dim_idx');
            $table->index('editor_user_id', 'airev_editor_idx');
            $table->index('subj_growth_stage', 'airev_stage_idx');
            $table->index('program_category_id', 'airev_progcat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generation_events', function (Blueprint $table) {
            $table->dropIndex('aigen_dim_idx');
            $table->dropColumn(['subj_cohort', 'subj_growth_stage', 'subj_grade_level', 'subj_gender']);
        });
        Schema::table('ai_revision_events', function (Blueprint $table) {
            $table->dropIndex('airev_dim_idx');
            $table->dropIndex('airev_editor_idx');
            $table->dropIndex('airev_stage_idx');
            $table->dropIndex('airev_progcat_idx');
            $table->dropColumn(['subj_cohort', 'subj_growth_stage', 'subj_grade_level', 'subj_gender',
                'support_category', 'program_category_id', 'dim_meta']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習基盤 S4c: Layer2集計。修正(AI出力→人間)の傾向を期間×次元でロールアップする。
 *
 *  - 集計対象は同意済みデータのみ(集計ジョブ側で現在の同意でフィルタ)。
 *  - k-匿名: distinct_students(sample_n) < 5 のセルは保存しない(集計ジョブ側で除外)。
 *  - 次元は NULL = その軸は「全体(ALL)」を意味する OLAP ロールアップ。facet 列で行の粒度を明示。
 *  - 冪等再計算: 期間単位で delete → insert。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_edit_metrics', function (Blueprint $table) {
            $table->id();
            $table->char('period_ym', 7)->comment('集計対象月 例 2026-06');
            $table->string('facet', 24)->comment('行の粒度: company/classroom/cohort/growth_stage/author/document_type/support_category/program_category');
            $table->unsignedBigInteger('company_id');
            // 次元(facet に応じて非NULL。それ以外はNULL=ALL)
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('subj_cohort', 16)->nullable();
            $table->string('subj_growth_stage', 4)->nullable();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 40)->nullable();
            $table->string('support_category', 40)->nullable();
            $table->unsignedBigInteger('program_category_id')->nullable();
            // メトリクス
            $table->unsignedInteger('gen_count')->default(0)->comment('生成イベント数(分母候補)');
            $table->unsignedInteger('revision_count')->default(0)->comment('変更セクション数');
            $table->unsignedInteger('edited_document_count')->default(0)->comment('修正が入った文書数(distinct document_id)');
            $table->unsignedInteger('distinct_students')->default(0)->comment('対象児童数=sample_n(k-匿名)');
            $table->float('edit_rate')->nullable()->comment('edited_document_count / gen_count(AI生成のうち修正された割合)');
            $table->float('change_ratio_avg')->nullable();
            $table->float('change_ratio_p50')->nullable();
            $table->float('change_ratio_p90')->nullable();
            $table->float('ai_acceptance')->nullable()->comment('1 - change_ratio_avg');
            $table->jsonb('top_reason_categories')->nullable()->comment('[{category_id,count}] 上位');
            $table->timestampTz('computed_at')->useCurrent();

            $table->index(['period_ym', 'company_id', 'facet'], 'aiem_period_company_facet_idx');
            $table->index(['company_id', 'facet'], 'aiem_company_facet_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_edit_metrics');
    }
};

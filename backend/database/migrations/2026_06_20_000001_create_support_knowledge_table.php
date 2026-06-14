<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 支援知蒸留 D4: 支援知DB(L3)。条件(対象コホート×成長段階)別に、法人内で蒸留した
 * 「どんな支援が実施され、どんな成果傾向か」をまとめる。横断検索(D5)の読み元。
 *
 * スコープ: 法人(company)内。全国横断は法務4点+匿名化クリア後にスキーマ拡張で対応。
 * PII安全: 実施傾向はコード+件数、成果は平均値、例示はマスク済抜粋。k-匿名(sample_n>=5)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_knowledge', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            // 条件(L3キー)
            $table->string('cohort', 16)->nullable()->comment('preschool/elementary/junior_high/high_school');
            $table->string('growth_stage', 4)->nullable()->comment('S1〜S6');
            $table->unsignedInteger('sample_n')->default(0)->comment('対象児童数(k-匿名)');
            // 実施傾向(支援)
            $table->jsonb('top_support_categories')->nullable()->comment('[{code,count}] 5領域上位');
            $table->jsonb('top_programs')->nullable()->comment('[{program_category_id,count}] 実施プログラム上位');
            // 成果(outcome)平均
            $table->float('outcome_objective_delta_avg')->nullable();
            $table->float('outcome_monitoring_pct_avg')->nullable();
            $table->float('outcome_agreement_avg')->nullable();
            // 見本抜粋(マスク済・PII無し)
            $table->jsonb('exemplar_excerpts')->nullable()->comment('[{section,text}] マスク済の確定/見本記述例');
            $table->unsignedInteger('exemplar_count')->default(0);
            $table->timestampTz('computed_at')->useCurrent();

            $table->unique(['company_id', 'cohort', 'growth_stage'], 'support_knowledge_condition_uq');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_knowledge');
    }
};

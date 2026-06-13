<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習知識基盤 S1: 同意基盤。
 *
 * 企画書 §12。学習利用(二次利用=要配慮個人情報)は運用生成とは別のオプトイン同意を必須とする。
 * 3層 × 目的別(AND条件): improvement_aggregate(施設) / model_learning(保護者・本人)。
 * 正史は append-only の consent_records。companies/students には現在値の非正規化フラグ(キャッシュ)。
 *
 * 本フェーズは同意の記録・判定基盤のみ。Layer3学習昇格の有効化は法務4点確定後(S8)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        // 同意定義(目的×版×文面) --------------------------------------------------------
        Schema::create('consent_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('consent_key', 40)->comment('service_generation/improvement_aggregate/model_learning/local_ai');
            $table->string('subject_type', 20)->comment('company/student/user');
            $table->string('title');
            $table->text('description')->nullable()->comment('同意文面(表示用)');
            $table->unsignedInteger('version')->default(1)->comment('文面の版。変われば再同意要求');
            $table->string('policy_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['consent_key', 'version']);
        });

        // 同意記録(append-only。撤回も新規行で積む) ------------------------------------
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_definition_id')->nullable()
                ->constrained('consent_definitions')->nullOnDelete();
            $table->string('consent_key', 40)->comment('検索用に非正規化');
            $table->string('subject_type', 20)->comment('company/student/user');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('company_id')->nullable()->comment('テナント絞り込み用');
            $table->string('state', 12)->comment('granted/revoked');
            $table->unsignedInteger('version')->nullable()->comment('同意時点の文面版');
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('granted_by_role', 24)->nullable()->comment('company_admin/guardian/student/staff_on_behalf');
            $table->string('acquisition_method', 24)->nullable()->comment('web_ui/paper_form/mynameis_sync/contract');
            $table->string('evidence_ref')->nullable()->comment('紙同意スキャン保管ID等');
            $table->timestampTz('acquired_at')->useCurrent();
            $table->timestampTz('effective_from')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent(); // append-only

            $table->index(['subject_type', 'subject_id', 'consent_key']);
            $table->index(['company_id', 'consent_key']);
        });

        // 現在値の非正規化フラグ(キャッシュ。正史は consent_records) ---------------------
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('ai_consent_aggregate')->default(false)
                ->comment('improvement_aggregate(Layer2統計)の現在値');
            $table->timestampTz('ai_consent_aggregate_at')->nullable();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->boolean('ai_consent_learning')->default(false)
                ->comment('model_learning(Layer3学習)の現在値');
            $table->timestampTz('ai_consent_learning_at')->nullable();
            $table->unsignedInteger('ai_consent_learning_version')->nullable()->comment('同意した文面版');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['ai_consent_learning', 'ai_consent_learning_at', 'ai_consent_learning_version']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ai_consent_aggregate', 'ai_consent_aggregate_at']);
        });
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('consent_definitions');
    }
};

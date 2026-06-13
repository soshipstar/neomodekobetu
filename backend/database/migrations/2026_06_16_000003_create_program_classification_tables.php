<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習基盤 S4a: 実施プログラム分類の基盤。
 *
 *  - program_categories: 5領域 × プログラム種別 の統制語彙(固定seed + 法人別/動的昇格)。
 *  - program_classifications: 記録(連絡帳/支援詳細/活動マスタ等)への分類付与(多相)。
 *  - program_category_candidates: 自由入力の新カテゴリ候補(昇格待ち。動的タクソノミー)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_categories', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 40)->nullable()->comment('5領域 health_life/.../social_relations。横断はnull');
            $table->string('code', 64);
            $table->string('label_ja', 100);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->jsonb('aliases')->nullable()->comment('自動分類用キーワード配列');
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('company_id')->nullable()->comment('NULL=全社共通(固定/横断昇格)');
            $table->boolean('is_seeded')->default(false);
            $table->string('status', 16)->default('active')->comment('active/archived');
            $table->unsignedInteger('sort_order')->default(100);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status', 'domain']);
        });

        Schema::create('program_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('classifiable_type', 40)->comment('daily_record/support_plan_detail/monitoring_detail/activity_support_plan');
            $table->unsignedBigInteger('classifiable_id');
            $table->foreignId('program_category_id')->constrained('program_categories')->cascadeOnDelete();
            $table->string('method', 16)->default('rule')->comment('rule/embedding/manual');
            $table->float('confidence')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['classifiable_type', 'classifiable_id'], 'progcls_target_idx');
            $table->index('program_category_id', 'progcls_cat_idx');
        });

        Schema::create('program_category_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('normalized_text', 255);
            $table->unsignedInteger('frequency')->default(1);
            $table->unsignedInteger('distinct_users')->default(1);
            $table->float('nearest_category_sim')->nullable();
            $table->string('status', 16)->default('pending')->comment('pending/approved/rejected/merged');
            $table->unsignedBigInteger('merged_into_category_id')->nullable();
            $table->jsonb('detection_meta')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_category_candidates');
        Schema::dropIfExists('program_classifications');
        Schema::dropIfExists('program_categories');
    }
};

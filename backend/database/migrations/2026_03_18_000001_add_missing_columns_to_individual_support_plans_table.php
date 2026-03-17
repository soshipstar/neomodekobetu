<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // S-001: Add missing columns to individual_support_plans
        //
        // These columns exist in the model's $fillable but are not yet in the
        // database schema (neither in the base create nor the legacy migration).
        // =====================================================================
        Schema::table('individual_support_plans', function (Blueprint $table) {
            // consent_name: 同意者名 (referenced by frontend as manager_name -> consent_name mapping)
            if (! Schema::hasColumn('individual_support_plans', 'consent_name')) {
                $table->string('consent_name')->nullable()->comment('同意者名');
            }
            // is_draft: 下書きフラグ (legacy MySQL had is_draft boolean)
            if (! Schema::hasColumn('individual_support_plans', 'is_draft')) {
                $table->boolean('is_draft')->default(true)->comment('下書きフラグ');
            }
            // guardian_review_comment_at: 保護者レビューコメント日時
            if (! Schema::hasColumn('individual_support_plans', 'guardian_review_comment_at')) {
                $table->timestampTz('guardian_review_comment_at')->nullable()->comment('保護者レビューコメント日時');
            }
            // staff_signature_image: Base64エンコードされた職員署名画像
            if (! Schema::hasColumn('individual_support_plans', 'staff_signature_image')) {
                $table->text('staff_signature_image')->nullable()->comment('Base64職員署名画像');
            }
            // guardian_signature_image: Base64エンコードされた保護者署名画像
            if (! Schema::hasColumn('individual_support_plans', 'guardian_signature_image')) {
                $table->text('guardian_signature_image')->nullable()->comment('Base64保護者署名画像');
            }
        });

        // =====================================================================
        // S-001b: Add missing column to support_plan_details
        //
        // The frontend uses 'category' as a separate field from 'domain'.
        // 'domain' = broad area (健康・生活, 運動・感覚 etc.)
        // 'category' = grouping (本人支援, 家族支援, 地域支援)
        // =====================================================================
        Schema::table('support_plan_details', function (Blueprint $table) {
            if (! Schema::hasColumn('support_plan_details', 'category')) {
                $table->string('category', 100)->nullable()->after('domain')->comment('カテゴリ（本人支援、家族支援等）');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_plan_details', function (Blueprint $table) {
            $table->dropColumn(['category']);
        });

        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn([
                'consent_name',
                'is_draft',
                'guardian_review_comment_at',
                'staff_signature_image',
                'guardian_signature_image',
            ]);
        });
    }
};

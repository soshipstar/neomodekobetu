<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 施設記録基準(E1): 施設独自の「記録基準」を明示定義する。
 *
 * これまで記録の方向性は蓄積からの暗黙学習(S5 WritingProfileService)のみだった。
 * 企業管理者がGPT5.4との対話で施設独自の基準を作成・確定し、AI生成プロンプトに注入することで
 * 「基準に沿った下書き」が出て、スタッフが基準を自然に身につける(育成)。法人ごと1件・版管理。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_writing_standards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->string('status', 12)->default('draft')->comment('draft/active');
            $table->unsignedInteger('version')->default(1);
            // 構造化セクション {tone, required_points[], terminology[], avoid[], good_examples[], bad_examples[]}
            $table->jsonb('sections')->nullable();
            // 生成プロンプト注入用にコンパイルした基準テキスト
            $table->text('guidance_text')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_writing_standards');
    }
};

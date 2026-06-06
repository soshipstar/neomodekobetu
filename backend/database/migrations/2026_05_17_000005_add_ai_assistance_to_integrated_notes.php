<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * integrated_notes (連絡帳統合文) に AI 関与情報を追加する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R5 (2026-05-17):
 *  - V7 説明可能性 / 表 3-8 ③ AI 生成であることの明示
 *  - 4.2.5 (3) ヒューマン・イン・ザ・ループ: 介入ポイントの明確化
 *  - 4.2.5 (4) 透明性・説明可能性: AI が生成した応答であることの明示
 *
 * カラム:
 *  - ai_assisted        boolean   AI 補助で作成されたか
 *  - ai_review_status   string    pending / reviewed / modified / rejected
 *  - ai_reviewed_by     fk users  最終承認した職員
 *  - ai_reviewed_at     timestamp 承認時刻
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrated_notes', function (Blueprint $table) {
            $table->boolean('ai_assisted')->default(false)->after('integrated_content')
                ->comment('AI 補助で下書きが作成された場合 true');
            $table->string('ai_review_status', 20)->nullable()->after('ai_assisted')
                ->comment('pending / reviewed / modified / rejected');
            $table->foreignId('ai_reviewed_by')->nullable()
                ->after('ai_review_status')
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('ai_reviewed_at')->nullable()->after('ai_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('integrated_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_reviewed_by');
            $table->dropColumn(['ai_assisted', 'ai_review_status', 'ai_reviewed_at']);
        });
    }
};

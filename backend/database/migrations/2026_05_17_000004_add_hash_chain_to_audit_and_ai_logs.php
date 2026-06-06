<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 監査ログ (audit_logs) と AI 生成ログ (ai_generation_logs) に
 * ハッシュチェーン用カラムを追加する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R9 (2026-05-17):
 *  - V6 セキュリティ確保 / 表 3-7 ⑤ 「改ざん不可能な監査ログ」
 *  - V10 検証可能性 / 表 3-11 ③ 「生成物の作成・修正・承認の履歴が保持され、改ざん防止が実装」
 *
 * 設計:
 *  - 各行に row_hash (sha256, 64 文字 hex) を計算して保存
 *  - row_hash の計算入力は (prev_row_hash, key fields の serialize)
 *  - prev_row_hash は同 user_id / グローバル の前行の row_hash
 *  - 検証コマンド audit-logs:verify-chain で連鎖の整合性を確認できる
 *
 * 注意: 既存行については row_hash を遡って計算するため、本マイグレーション後に
 * 一度 `php artisan audit-logs:backfill-hash` の実行が必要 (別途実装)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('row_hash', 64)->nullable()->after('user_agent')
                ->comment('行の sha256 ハッシュ (改ざん検知用)');
            $table->string('prev_row_hash', 64)->nullable()->after('row_hash')
                ->comment('チェーンの直前行 row_hash');
            $table->index('row_hash');
        });

        Schema::table('ai_generation_logs', function (Blueprint $table) {
            $table->string('row_hash', 64)->nullable()->after('duration_ms')
                ->comment('行の sha256 ハッシュ (改ざん検知用)');
            $table->string('prev_row_hash', 64)->nullable()->after('row_hash')
                ->comment('チェーンの直前行 row_hash');
            $table->index('row_hash');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['row_hash']);
            $table->dropColumn(['row_hash', 'prev_row_hash']);
        });
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            $table->dropIndex(['row_hash']);
            $table->dropColumn(['row_hash', 'prev_row_hash']);
        });
    }
};

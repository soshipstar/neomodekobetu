<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 見本キュレーション: 修正イベントに「学習の見本」採否フラグを追加する。
 *
 * 自己改善ループ(S5)の例示が低品質記録(ノイズ)を取り込み学習を汚すのを防ぐため、
 *  - exemplar_status='adopted'  : 主任/管理者が「見本」に採用(学習で優先)
 *  - exemplar_status='excluded' : 学習から明示除外
 *  - null                        : 未判定(自動品質フィルタの対象)
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_revision_events', function (Blueprint $table) {
            $table->string('exemplar_status', 12)->nullable()
                ->comment('adopted=見本採用 / excluded=学習除外 / null=未判定');
            $table->foreignId('curated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('curated_at')->nullable();

            $table->index(['company_id', 'document_type', 'exemplar_status'], 'airev_exemplar_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_revision_events', function (Blueprint $table) {
            $table->dropIndex('airev_exemplar_idx');
            $table->dropColumn(['exemplar_status', 'curated_by', 'curated_at']);
        });
    }
};

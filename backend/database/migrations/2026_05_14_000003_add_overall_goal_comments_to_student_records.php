<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_records に「個別支援計画の短期・長期目標に対するコメント」を保存する
 * 2 カラムを追加する。
 *
 * 背景: 五領域 (健康・生活, 運動・感覚, 認知・行動, 言語・コミュニケーション,
 * 人間関係・社会性) の領域別目標コメントは domain_goal_quotes (jsonb) に
 * 保持済み (2026_05_14_000002)。それとは別に、個別支援計画の「短期目標」
 * 「長期目標」全体に対するコメントを 個別メモ の直前に表示するため、専用列を
 * 用意する。
 *
 * - short_term_goal_comment: 短期目標に対するスタッフのコメント
 * - long_term_goal_comment : 長期目標に対するスタッフのコメント
 *
 * AI 統合プロンプトでも値があれば「【個別支援計画の短期/長期目標に関する所感】」
 * として組み込み、文章生成に活用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->text('short_term_goal_comment')->nullable()->after('domain_goal_quotes')
                ->comment('個別支援計画の短期目標に対するコメント');
            $table->text('long_term_goal_comment')->nullable()->after('short_term_goal_comment')
                ->comment('個別支援計画の長期目標に対するコメント');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn(['short_term_goal_comment', 'long_term_goal_comment']);
        });
    }
};

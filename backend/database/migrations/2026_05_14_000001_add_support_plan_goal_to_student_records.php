<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 連絡帳の生徒記録 (student_records) に、個別支援計画の目標スナップショットと
 * その目標に対するコメントを保存する 2 カラムを追加する。
 *
 * 報告: 「連絡帳にも個別支援計画の目標を選び、それに対するコメントをつける
 *        機能を搭載してほしい」
 *
 * - goal_text: 当該記録時点での目標テキストのスナップショット
 *   (個別支援計画は後から更新されうるため、その時点の文言を保持)
 * - goal_comment: 目標に対するスタッフのコメント (任意)
 *
 * 統合連絡帳の AI プロンプトでも goal_text / goal_comment が存在すれば
 * それを「【支援計画の目標】」として組み込み、文章生成に活用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->text('goal_text')->nullable()->after('notes')
                ->comment('個別支援計画の目標スナップショット (短期 or 長期、フリーテキスト)');
            $table->text('goal_comment')->nullable()->after('goal_text')
                ->comment('目標に対するコメント (チェックがあった時のみ保存)');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn(['goal_text', 'goal_comment']);
        });
    }
};

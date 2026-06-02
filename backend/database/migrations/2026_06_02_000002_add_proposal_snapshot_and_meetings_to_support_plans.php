<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 個別支援計画に「原案スナップショット」と「個別支援会議 議事録」を追加。
 *
 * 要望:
 *  - 原案(保護者に提示した時点の内容)を、本案に確定・編集した後も参照したい。
 *  - 原案に対する保護者コメントと原案をセットで確認したい
 *    (保護者コメントは既存の individual_support_plans.guardian_review_comment)。
 *  - 原案を保護者に提示する前に行う「個別支援会議」の議事録を同じページで確認したい。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 原案スナップショット: 保護者へ確認依頼(proposal)した時点の計画内容を保存。
        // 本体(目標等)と明細をまとめて JSON で保持し、後で本案を編集しても原案が残る。
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->jsonb('proposal_snapshot')->nullable()
                ->comment('保護者へ確認依頼した時点の原案内容(本体+明細)のスナップショット');
            $table->timestampTz('proposal_snapshot_at')->nullable()
                ->comment('原案スナップショットを保存した日時(=保護者へ確認依頼した日時)');
        });

        // 個別支援会議 議事録: 各計画(原案)に紐づく。会議日・出席者・協議内容。
        Schema::create('support_plan_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('individual_support_plans')->cascadeOnDelete();
            $table->date('meeting_date')->nullable()->comment('会議日');
            $table->text('attendees')->nullable()->comment('出席者');
            $table->text('discussion')->nullable()->comment('協議内容(原案についての議論)');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_plan_meetings');
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn(['proposal_snapshot', 'proposal_snapshot_at']);
        });
    }
};

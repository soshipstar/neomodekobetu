<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 放課後等デイサービスのヒヤリハット記録テーブル。
     *
     * 項目は業界で一般的に使われる内容を網羅:
     *  - 基本情報: 発生日時・場所・対象児童・記録者・確認者
     *  - 状況: 発生前の活動、児童の状態、発生状況
     *  - 分析: 危険度、事故分類、原因 (環境/人的/その他)
     *  - 対応: 即時対応、保護者連絡、医療受診、怪我の内容
     *  - 再発防止: 改善策、環境整備、スタッフ共有
     *  - 関連: 元になった連絡帳レコード (AI 検出由来かどうか)
     */
    public function up(): void
    {
        Schema::create('hiyari_hatto_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users'); // 記録者
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users'); // 管理者確認

            // 発生情報
            $table->timestampTz('occurred_at');
            $table->string('location', 255)->nullable(); // 発生場所
            $table->text('activity_before')->nullable(); // 発生前の活動
            $table->text('student_condition')->nullable(); // 児童の状態
            $table->text('situation'); // 発生状況（必須）

            // 分析
            $table->string('severity', 20)->default('low'); // low / medium / high
            $table->string('category', 50)->nullable(); // fall, collision, choking, missing, allergy, conflict, self_harm, other
            $table->text('cause_environmental')->nullable();
            $table->text('cause_human')->nullable();
            $table->text('cause_other')->nullable();

            // 対応
            $table->text('immediate_response')->nullable();
            $table->boolean('guardian_notified')->default(false);
            $table->timestampTz('guardian_notified_at')->nullable();
            $table->text('guardian_notification_content')->nullable();
            $table->boolean('medical_treatment')->default(false);
            $table->text('medical_detail')->nullable();
            $table->text('injury_description')->nullable();

            // 再発防止
            $table->text('prevention_measures')->nullable();
            $table->text('environment_improvements')->nullable();
            $table->text('staff_sharing_notes')->nullable();

            // 関連
            $table->foreignId('source_daily_record_id')->nullable()->constrained('daily_records')->nullOnDelete();
            $table->string('source_type', 30)->default('manual'); // manual / integrated_note_ai

            // ステータス
            $table->string('status', 20)->default('draft'); // draft / submitted / confirmed

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['classroom_id', 'occurred_at']);
            $table->index('student_id');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiyari_hatto_records');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 異常検知 (ApiAnomalyDetectionService) が検出したアラートの永続化テーブル。
 *
 * 用途:
 *   - /admin/security-alerts 画面で履歴を一覧 / 対処済みにできるようにする。
 *   - 通知だけだと流れてしまうため、検出ごとに 1 レコード残す。
 *
 * 1 検出 = 1 レコード。(user_id, rule, detected_hour) でユニークにし、
 * 同じ時間帯の同じルールを重複保存しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule', 40);            // A_excessive_requests 等
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 255)->nullable();   // 検出時点の氏名スナップショット
            $table->string('user_type', 20)->nullable();
            $table->integer('count');              // 検出時の件数
            $table->string('title', 255);
            $table->text('body');
            // 検出した時間帯 (時単位に丸めた値)。同じ時間帯の重複保存防止に使う。
            $table->timestamp('detected_hour');
            $table->boolean('is_resolved')->default(false);
            $table->text('resolved_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['rule', 'user_id', 'detected_hour'], 'security_alerts_dedup_unique');
            $table->index(['is_resolved', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};

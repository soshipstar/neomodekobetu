<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * マスター管理者が企業の契約・表示設定を変更した履歴を残すための監査ログ。
 *
 * - master_user_id: 操作者（is_master = true のユーザー）
 * - company_id   : 対象企業
 * - action       : 'update_display_settings' / 'update_billing' / 'cancel_subscription' など
 * - before/after : 変更前後の差分 JSON（差分のみではなく対象キーの値そのもの）
 * - context      : 任意の追加情報（IP、UA、メモなど）
 *
 * 通常は append-only で、updated_at は使わない（行は変えない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('action', 100);
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_admin_audit_logs');
    }
};

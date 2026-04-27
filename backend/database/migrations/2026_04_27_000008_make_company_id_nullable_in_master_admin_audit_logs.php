<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * master_admin_audit_logs.company_id を nullable に変更。
 *
 * 代理店マスタの作成・更新・削除など「特定企業に紐付かない」操作も
 * このテーブルで監査ログとして記録するため、 company_id を NULL 許容にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_admin_audit_logs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // 既存データに NULL があると失敗するため、まず 0 で埋めてから NOT NULL に戻す。
        // ただし FK 制約があるので 0 はFK違反になる。down() は基本実行しない前提。
        // 必要時のみ手動でNULL行を削除してから ALTER する想定。
        Schema::table('master_admin_audit_logs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }
};

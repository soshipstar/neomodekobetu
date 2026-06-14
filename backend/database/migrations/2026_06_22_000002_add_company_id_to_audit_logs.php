<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * テナント分離(rank6): audit_logs に company_id を追加する。
 *
 * 現状 company_id 列が無く、監査ログ閲覧(AuditLogController)が施設横断で全社混在していた
 * (非マスター管理者が他施設の操作履歴を閲覧しうる)。3省2ガイドラインのアクセス管理・監査分離に
 * 適合させるため列+索引を追加し、既存行は実行者(user→classroom→company)から補完する。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained('companies')->nullOnDelete();
            $table->index('company_id', 'audit_logs_company_id_idx');
        });

        // 既存行の補完: 実行者の所属施設(user → classroom → company)。
        DB::statement(<<<'SQL'
            UPDATE audit_logs al
            SET company_id = c.company_id
            FROM users u
            JOIN classrooms c ON c.id = u.classroom_id
            WHERE u.id = al.user_id
              AND al.company_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_company_id_idx');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};

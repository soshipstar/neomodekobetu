<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 所属教室が設定されているのに所属企業が未設定のユーザーを backfill。
 * classrooms.company_id を参照して users.company_id を埋める。
 *
 * 不整合例:
 *  - 所属教室だけ入力された旧管理者/スタッフ
 *  - 企業選択をスキップして作成されたアカウント
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL の UPDATE ... FROM 構文で一括更新
        DB::statement(<<<'SQL'
            UPDATE users u
            SET company_id = c.company_id
            FROM classrooms c
            WHERE u.classroom_id = c.id
              AND u.classroom_id IS NOT NULL
              AND u.company_id IS NULL
              AND c.company_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        // backfill は冪等なので down は no-op。
        // 必要なら手動で users.company_id を NULL に戻すこと。
    }
};

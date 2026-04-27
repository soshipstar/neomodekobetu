<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * users.user_type の CHECK 制約に 'agent' を追加する。
 *
 * 既存の制約は ['admin', 'staff', 'guardian', 'tablet', 'student'] のみ許可。
 * 代理店ロール導入のため 'agent' を追加する。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 既存制約を一旦外す
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        // 既存環境（特に本番）には 'tablet_user' を含む古い user_type のレコードが残っている
        // 可能性があるため、許可リストには 'tablet_user' も含めて互換性を保つ。
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_user_type_check
            CHECK (user_type IN (
                'admin',
                'staff',
                'guardian',
                'tablet',
                'tablet_user',
                'student',
                'agent'
            ))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_user_type_check
            CHECK (user_type IN (
                'admin',
                'staff',
                'guardian',
                'tablet',
                'tablet_user',
                'student'
            ))
        SQL);
    }
};

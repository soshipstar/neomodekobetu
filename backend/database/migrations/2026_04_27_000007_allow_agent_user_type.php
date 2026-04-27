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
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_user_type_check
            CHECK ((user_type)::text = ANY (ARRAY[
                'admin'::varchar,
                'staff'::varchar,
                'guardian'::varchar,
                'tablet'::varchar,
                'student'::varchar,
                'agent'::varchar
            ]::text[]))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_user_type_check
            CHECK ((user_type)::text = ANY (ARRAY[
                'admin'::varchar,
                'staff'::varchar,
                'guardian'::varchar,
                'tablet'::varchar,
                'student'::varchar
            ]::text[]))
        SQL);
    }
};

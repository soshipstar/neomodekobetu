<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Option B: users.company_id カラムを削除し、
 * 正規化違反（推移的従属）を構造的に解消する。
 *
 * 以降 users の所属企業は常に users → classrooms → companies を
 * 辿って取得する。アプリ側では User::getCompanyIdAttribute() や
 * Company::users() (hasManyThrough) がこの導出を担う。
 *
 * 事前条件:
 *  - 2026_04_11_000001 で backfill 済み
 *  - 2026_04_11_000002 のトリガーは不要になるので本マイグレーションで drop
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. トリガーと関数を削除（カラムを参照しているため先に drop が必要）
        DB::unprepared('DROP TRIGGER IF EXISTS users_enforce_company_trigger ON users;');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_user_company_matches_classroom();');
        DB::unprepared('DROP TRIGGER IF EXISTS classrooms_cascade_company_trigger ON classrooms;');
        DB::unprepared('DROP FUNCTION IF EXISTS cascade_classroom_company_to_users();');

        // 2. 外部キー制約を削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        // 3. カラム削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }

    public function down(): void
    {
        // カラム再追加
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('classroom_id')
                ->constrained('companies')
                ->nullOnDelete();
        });

        // 既存データを classroom.company_id から再 backfill
        DB::statement(<<<'SQL'
            UPDATE users u
            SET company_id = c.company_id
            FROM classrooms c
            WHERE u.classroom_id = c.id
              AND u.classroom_id IS NOT NULL
              AND c.company_id IS NOT NULL
        SQL);

        // トリガーを再作成（2026_04_11_000002 と同内容）
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_user_company_matches_classroom()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.classroom_id IS NOT NULL THEN
                    SELECT company_id
                      INTO NEW.company_id
                      FROM classrooms
                     WHERE id = NEW.classroom_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER users_enforce_company_trigger
              BEFORE INSERT OR UPDATE OF classroom_id, company_id ON users
              FOR EACH ROW
              EXECUTE FUNCTION enforce_user_company_matches_classroom();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION cascade_classroom_company_to_users()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.company_id IS DISTINCT FROM OLD.company_id THEN
                    UPDATE users
                       SET company_id = NEW.company_id
                     WHERE classroom_id = NEW.id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER classrooms_cascade_company_trigger
              AFTER UPDATE OF company_id ON classrooms
              FOR EACH ROW
              EXECUTE FUNCTION cascade_classroom_company_to_users();
        SQL);
    }
};

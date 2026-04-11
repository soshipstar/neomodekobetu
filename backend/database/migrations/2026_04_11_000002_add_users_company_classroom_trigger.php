<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * users.company_id を users.classroom_id に追随させる PostgreSQL トリガー。
 *
 * アプリ層のバリデーションをすり抜ける直 SQL でも整合性が崩れないように、
 * INSERT / UPDATE 時に自動で classroom.company_id を NEW.company_id に反映する。
 *
 * 仕様:
 *  - classroom_id が NULL の場合: company_id はそのまま尊重する
 *    （例: 所属教室を持たないマスター管理者）
 *  - classroom_id が与えられた場合: classrooms.company_id の値で
 *    NEW.company_id を強制上書きする
 *  - 存在しない classroom_id を指定してきた場合は FK で弾かれる想定
 *
 * これにより以下の経路でも整合性が保証される:
 *  - Laravel 経由（既に normalizeCompanyFromClassroom で担保済）
 *  - 直接 SQL 実行（今回担保）
 *  - データ移行スクリプト
 *  - 将来追加される別アプリケーション
 */
return new class extends Migration
{
    public function up(): void
    {
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
            DROP TRIGGER IF EXISTS users_enforce_company_trigger ON users;
            CREATE TRIGGER users_enforce_company_trigger
              BEFORE INSERT OR UPDATE OF classroom_id, company_id ON users
              FOR EACH ROW
              EXECUTE FUNCTION enforce_user_company_matches_classroom();
        SQL);

        // classrooms.company_id が変更されたときは、所属する users 全員に伝播させる
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
            DROP TRIGGER IF EXISTS classrooms_cascade_company_trigger ON classrooms;
            CREATE TRIGGER classrooms_cascade_company_trigger
              AFTER UPDATE OF company_id ON classrooms
              FOR EACH ROW
              EXECUTE FUNCTION cascade_classroom_company_to_users();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS classrooms_cascade_company_trigger ON classrooms;');
        DB::unprepared('DROP FUNCTION IF EXISTS cascade_classroom_company_to_users();');
        DB::unprepared('DROP TRIGGER IF EXISTS users_enforce_company_trigger ON users;');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_user_company_matches_classroom();');
    }
};

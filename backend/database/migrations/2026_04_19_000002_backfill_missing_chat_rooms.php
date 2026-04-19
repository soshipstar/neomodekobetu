<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新アプリで追加された教室 (てらこや/ぶーけ中川/てらこやプラス 等) の生徒は
 * 生徒作成処理で chat_rooms を作っていなかったため、スタッフ側チャット画面に
 * 表示されない状態だった。guardian_id を持つ全生徒に chat_rooms を補充する。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO chat_rooms (student_id, guardian_id, created_at)
            SELECT s.id, s.guardian_id, NOW()
            FROM students s
            WHERE s.guardian_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM chat_rooms cr
                  WHERE cr.student_id = s.id AND cr.guardian_id = s.guardian_id
              )
        ");
    }

    public function down(): void
    {
        // 復元不可（どの chat_rooms が補充分かを識別する手段が無いため無操作）
    }
};

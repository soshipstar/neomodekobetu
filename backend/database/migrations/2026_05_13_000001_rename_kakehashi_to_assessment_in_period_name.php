<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 既存 kakehashi_periods.period_name に保存されている自動生成名
 * 「N回目かけはし（生徒名）」を「N回目アセスメント（生徒名）」に置換する。
 *
 * 直前の screen 修正 (commit 65bf6ed) で新規生成分は「アセスメント」になっているが、
 * すでに DB に保存されている過去レコードは「かけはし」のまま残っていた。
 * 報告者から「1回目かけはしのかけはしも変更してください。これは変更しても大丈夫です」
 * との指示があったため、ここで一括置換する。
 *
 * 影響対象: kakehashi_periods.period_name のみ。
 * 他テーブル (kakehashi_staff, kakehashi_guardian, chat_messages 等) の本文は
 * ユーザー入力 / AI 生成テキストの可能性があるため触らない。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('kakehashi_periods')
            ->where('period_name', 'like', '%かけはし%')
            ->update([
                'period_name' => DB::raw("REPLACE(period_name, 'かけはし', 'アセスメント')"),
            ]);
    }

    public function down(): void
    {
        DB::table('kakehashi_periods')
            ->where('period_name', 'like', '%アセスメント%')
            ->update([
                'period_name' => DB::raw("REPLACE(period_name, 'アセスメント', 'かけはし')"),
            ]);
    }
};

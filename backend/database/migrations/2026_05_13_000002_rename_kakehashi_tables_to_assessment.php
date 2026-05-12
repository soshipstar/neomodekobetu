<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「かけはし」から「アセスメント」への用語統一の最終段階として、
 * 3つのテーブル名を kakehashi_* から assessment_* にリネームする。
 *
 * PostgreSQL の ALTER TABLE RENAME はインデックス・外部キー・データを保持したまま
 * 即時に名前を切り替える。Schema::rename はこれをラップする。
 *
 * 併せて audit_logs.target_table に保存されていた 'kakehashi_guardian' / 'kakehashi_staff'
 * の文字列値も新名称に更新する。これにより SendDeadlineNotificationsJob の
 * isAlreadySentToday() (target_table を照合する) が連続性を保ち、本日中の
 * 重複通知を防げる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('kakehashi_periods',  'assessment_periods');
        Schema::rename('kakehashi_staff',    'assessment_staff');
        Schema::rename('kakehashi_guardian', 'assessment_guardian');

        DB::table('audit_logs')
            ->where('target_table', 'kakehashi_guardian')
            ->update(['target_table' => 'assessment_guardian']);
        DB::table('audit_logs')
            ->where('target_table', 'kakehashi_staff')
            ->update(['target_table' => 'assessment_staff']);
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->where('target_table', 'assessment_guardian')
            ->update(['target_table' => 'kakehashi_guardian']);
        DB::table('audit_logs')
            ->where('target_table', 'assessment_staff')
            ->update(['target_table' => 'kakehashi_staff']);

        Schema::rename('assessment_guardian', 'kakehashi_guardian');
        Schema::rename('assessment_staff',    'kakehashi_staff');
        Schema::rename('assessment_periods',  'kakehashi_periods');
    }
};

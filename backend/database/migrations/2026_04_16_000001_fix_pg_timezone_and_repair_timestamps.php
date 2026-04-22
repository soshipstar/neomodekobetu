<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * APP_TIMEZONE=Asia/Tokyo 設定 (2026-04-15 14:36 UTC) 以降、
 * PHP の now() が JST を返すのに PG セッションが UTC のまま → JST 値が UTC として保存されていた。
 * 影響期間: 2026-04-15 14:36 UTC ～ このマイグレーション適用まで
 *
 * 修復方法: 影響期間内の全タイムスタンプから 9 時間を引く
 * (JST として格納された値を本来の UTC に戻す)
 */
return new class extends Migration
{
    /**
     * 影響を受けるテーブルとタイムスタンプカラムの一覧
     * [table => [columns...]]
     */
    private array $targets = [
        'integrated_notes'       => ['sent_at', 'guardian_confirmed_at', 'created_at', 'updated_at'],
        'chat_messages'          => ['read_at', 'created_at', 'updated_at'],
        'staff_chat_messages'    => ['created_at', 'updated_at'],
        'notifications'          => ['read_at', 'created_at', 'updated_at'],
        'chat_rooms'             => ['last_message_at', 'updated_at'],
        'staff_chat_reads'       => ['read_at'],
        'chat_message_staff_reads' => ['read_at'],
        'chat_room_pins'         => ['pinned_at'],
        'daily_records'          => ['created_at', 'updated_at'],
        'student_records'        => ['created_at', 'updated_at'],
        'hiyari_hatto_records'   => ['occurred_at', 'guardian_notified_at', 'created_at', 'updated_at'],
        'announcements'          => ['published_at', 'created_at', 'updated_at'],
        'announcement_reads'     => ['read_at', 'created_at', 'updated_at'],
        // confirmed_date は 2026_04_15_000002 で既に -9h 済み → 除外
        'meeting_requests'       => ['confirmed_at', 'completed_at', 'created_at', 'updated_at'],
        'login_attempts'         => ['attempted_at', 'created_at'],
        'bug_reports'            => ['created_at', 'updated_at'],
        'classroom_photos'       => ['created_at', 'updated_at'],
        'send_history'           => ['sent_at', 'read_at', 'created_at', 'updated_at'],
        'student_chat_rooms'     => ['last_message_at', 'created_at', 'updated_at'],
        'student_chat_messages'  => ['created_at', 'updated_at'],
        'absence_notifications'  => ['makeup_approved_at', 'created_at', 'updated_at'],
        'absence_response_records' => ['sent_at', 'guardian_confirmed_at', 'created_at', 'updated_at'],
        'personal_access_tokens' => ['last_used_at', 'created_at', 'updated_at'],
    ];

    /** 影響開始: APP_TIMEZONE=Asia/Tokyo がデプロイされた時刻 (UTC) */
    private string $cutoff = '2026-04-15 14:36:00+00';

    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            // テーブルが存在しない場合はスキップ
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            foreach ($columns as $col) {
                if (!DB::getSchemaBuilder()->hasColumn($table, $col)) {
                    continue;
                }

                // created_at/updated_at は影響期間以降のレコードのみ修正
                // それ以外のカラムは、値自体が影響期間内にあるもののみ修正
                $hasCreatedAt = DB::getSchemaBuilder()->hasColumn($table, 'created_at');

                if (in_array($col, ['created_at', 'updated_at'], true)) {
                    DB::statement("
                        UPDATE \"{$table}\"
                        SET \"{$col}\" = \"{$col}\" - INTERVAL '9 hours'
                        WHERE \"{$col}\" > ?
                    ", [$this->cutoff]);
                } elseif ($hasCreatedAt) {
                    DB::statement("
                        UPDATE \"{$table}\"
                        SET \"{$col}\" = \"{$col}\" - INTERVAL '9 hours'
                        WHERE \"created_at\" > ?
                        AND \"{$col}\" IS NOT NULL
                    ", [$this->cutoff]);
                } else {
                    // created_at がないテーブルは値自体で判定
                    DB::statement("
                        UPDATE \"{$table}\"
                        SET \"{$col}\" = \"{$col}\" - INTERVAL '9 hours'
                        WHERE \"{$col}\" > ?
                    ", [$this->cutoff]);
                }
            }
        }

    }

    public function down(): void
    {
        // 逆操作: +9 hours
        foreach ($this->targets as $table => $columns) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                if (!DB::getSchemaBuilder()->hasColumn($table, $col)) {
                    continue;
                }
                $hasCreatedAt = DB::getSchemaBuilder()->hasColumn($table, 'created_at');

                if (in_array($col, ['created_at', 'updated_at'], true)) {
                    DB::statement("
                        UPDATE \"{$table}\"
                        SET \"{$col}\" = \"{$col}\" + INTERVAL '9 hours'
                        WHERE \"{$col}\" > ?::timestamptz - INTERVAL '9 hours'
                    ", [$this->cutoff]);
                } elseif ($hasCreatedAt) {
                    DB::statement("
                        UPDATE \"{$table}\"
                        SET \"{$col}\" = \"{$col}\" + INTERVAL '9 hours'
                        WHERE \"created_at\" > ?::timestamptz - INTERVAL '9 hours'
                        AND \"{$col}\" IS NOT NULL
                    ", [$this->cutoff]);
                } else {
                    DB::statement("
                        UPDATE \"{$table}\"
                        SET \"{$col}\" = \"{$col}\" + INTERVAL '9 hours'
                        WHERE \"{$col}\" > ?::timestamptz - INTERVAL '9 hours'
                    ", [$this->cutoff]);
                }
            }
        }
    }
};

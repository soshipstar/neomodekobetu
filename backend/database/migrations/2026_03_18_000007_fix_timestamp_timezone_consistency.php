<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S-012: timestamp → timestamp with time zone に統一
 *
 * 2026_03_11_100000 等で追加されたカラムが timezone なしで定義されていた。
 * 他の全カラムは timestampTz を使用しているため統一する。
 */
return new class extends Migration
{
    private array $columns = [
        'individual_support_plans' => ['guardian_confirmed_at', 'basis_generated_at'],
        'meeting_requests'        => ['confirmed_at', 'completed_at'],
        'monitoring_details'      => ['created_at', 'updated_at'],
        'send_history'            => ['sent_at', 'read_at'],
        'activity_support_plans'  => ['created_at', 'updated_at'],
        'staff_chat_rooms'        => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col) {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$col}\" TYPE timestamp with time zone USING \"{$col}\" AT TIME ZONE 'Asia/Tokyo'");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col) {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$col}\" TYPE timestamp without time zone");
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026_04_16_000001 の修復マイグレーションで bug_reports ID=14,15,16 が
 * 過補正され -9h 余計に引かれていた（報告は 4/16 午後なのに早朝表示）。
 * 該当 3 件の created_at / updated_at に +9h を戻す。
 */
return new class extends Migration
{
    private array $ids = [14, 15, 16];

    public function up(): void
    {
        DB::table('bug_reports')
            ->whereIn('id', $this->ids)
            ->update([
                'created_at' => DB::raw("created_at + INTERVAL '9 hours'"),
                'updated_at' => DB::raw("updated_at + INTERVAL '9 hours'"),
            ]);
    }

    public function down(): void
    {
        DB::table('bug_reports')
            ->whereIn('id', $this->ids)
            ->update([
                'created_at' => DB::raw("created_at - INTERVAL '9 hours'"),
                'updated_at' => DB::raw("updated_at - INTERVAL '9 hours'"),
            ]);
    }
};

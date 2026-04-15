<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * TZ-001 データ修復:
     * config/app.php の timezone 未設定により、新 Laravel API から確定された
     * meeting_requests.confirmed_date は naked datetime-local 入力が UTC 扱いで
     * 保存され、実際の JST より 9 時間先の値となっていた。
     *
     * 新システム経由で確定されたレコードのみ対象とする（confirmed_at が 2026-03-01
     * 以降のもの）。旧 MySQL から移行された candidate_dates/confirmed_date は
     * 対象外（confirmed_at が未設定または旧日付）。
     */
    public function up(): void
    {
        DB::table('meeting_requests')
            ->whereNotNull('confirmed_date')
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '>=', '2026-03-01')
            ->update([
                'confirmed_date' => DB::raw("confirmed_date - INTERVAL '9 hours'"),
            ]);
    }

    public function down(): void
    {
        DB::table('meeting_requests')
            ->whereNotNull('confirmed_date')
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '>=', '2026-03-01')
            ->update([
                'confirmed_date' => DB::raw("confirmed_date + INTERVAL '9 hours'"),
            ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * daily_records の (record_date, staff_id, activity_name) ユニーク制約を撤廃する。
 *
 * 背景: 同じ支援案(=同一活動名)で同日に2件以上の活動記録を作りたい運用がある
 *       (報告者要望)。この制約があると2件目の作成がブロックされていた。
 *       撤廃により Web の連絡帳保存は常に新規レコードとして作成される。
 *       ※タブレット経路(Tablet\TabletController::storeActivity)は独自の
 *         アプリ内重複チェックを持つため、DB制約撤廃の影響を受けない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_records', function (Blueprint $table) {
            $table->dropUnique(['record_date', 'staff_id', 'activity_name']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_records', function (Blueprint $table) {
            $table->unique(['record_date', 'staff_id', 'activity_name']);
        });
    }
};

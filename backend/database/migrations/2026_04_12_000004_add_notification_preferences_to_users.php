<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users.notification_preferences (jsonb) を追加する。
     *
     * キーは通知カテゴリ名で値は boolean:
     *  - chat          : チャットメッセージ
     *  - announcement  : お知らせ
     *  - meeting       : 面談予約
     *  - kakehashi     : かけはし依頼
     *  - monitoring    : モニタリング表
     *  - support_plan  : 個別支援計画依頼
     *  - submission    : 提出物依頼
     *  - absence       : 欠席連絡 (保護者→スタッフ系の既存)
     *  - quick         : 帰宅・到着などのクイック通知
     *
     * null (未設定) は「全カテゴリ有効」とみなす。キーが省略されている
     * 場合も有効扱い（後方互換）。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('notification_preferences')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * newsletter_settings に default_requests / default_others カラムを追加する。
     *
     * 施設通信設定で「お願い事項」「その他」の既定文言を保存するためのテキスト
     * カラム。NewsletterSetting モデルと NewsletterSettingController は既に
     * このカラムを参照しているが、元 migration に含まれておらず本番で
     * SQLSTATE[42703] 「column does not exist」が連発していた。
     */
    public function up(): void
    {
        Schema::table('newsletter_settings', function (Blueprint $table) {
            $table->text('default_requests')->nullable()->after('custom_sections');
            $table->text('default_others')->nullable()->after('default_requests');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_settings', function (Blueprint $table) {
            $table->dropColumn(['default_requests', 'default_others']);
        });
    }
};

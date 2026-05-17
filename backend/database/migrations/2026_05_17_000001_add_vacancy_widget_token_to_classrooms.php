<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 教室の HP 埋め込みウィジェット用 公開トークンを追加する。
 *
 * 用途:
 *   教室の HP (任意の外部ホスティング) に iframe で空き状況を埋め込めるよう、
 *   公開アクセス可能なエンドポイント
 *     GET /api/widget/vacancy/{token}        (HTML iframe ページ)
 *     GET /api/public/vacancy/{token}/data   (JSON API)
 *   の鍵となるトークンを保存する。
 *
 * セキュリティ:
 *   - トークンは URL-safe な 32 文字のランダム文字列
 *   - 漏洩時は管理者が再発行 (この列を null → 新値で上書き) して旧トークンを無効化可能
 *   - 公開する情報は曜日別の空き数のみ (個人情報・名前は一切返さない)
 *
 * 既存教室には null を保持し、admin が必要なときに発行する pull モデルにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('vacancy_widget_token', 64)->nullable()->after('settings');
            $table->unique('vacancy_widget_token');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['vacancy_widget_token']);
            $table->dropColumn('vacancy_widget_token');
        });
    }
};

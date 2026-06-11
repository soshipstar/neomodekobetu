<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 業務記録テーブルの staff/uploader 外部キーの削除挙動を cascade → nullOnDelete に修正。
 *
 * 放デイ業務リスク監査 SCHEMA-01/05/07 (P1):
 *  退職スタッフの users レコードを削除すると、cascadeOnDelete により
 *  そのスタッフに紐づく業務記録 (面談履歴・活動支援計画・教室写真) が
 *  連鎖削除され、確定済みの法定記録が消失するリスクがあった。
 *  スタッフ削除後も記録は保持し、staff_id / uploader_id を NULL にする。
 *
 * 対象:
 *  - meeting_requests.staff_id      (cascadeOnDelete → nullOnDelete)
 *  - activity_support_plans.staff_id (cascadeOnDelete → nullOnDelete)
 *  - classroom_photos.uploader_id    (RESTRICT/必須 → nullable + nullOnDelete)
 *
 * いずれも対象カラムは既に nullable か、本 migration で nullable 化する。
 */
return new class extends Migration
{
    public function up(): void
    {
        // meeting_requests.staff_id: 既に nullable。FK の削除挙動のみ変更。
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
        });

        // activity_support_plans.staff_id: 必須 → nullable + nullOnDelete。
        Schema::table('activity_support_plans', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });
        Schema::table('activity_support_plans', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->change();
        });
        Schema::table('activity_support_plans', function (Blueprint $table) {
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
        });

        // classroom_photos.uploader_id: 必須 → nullable + nullOnDelete。
        // 投稿者情報のみ NULL にし、写真ファイルと連絡帳添付参照は保持する。
        Schema::table('classroom_photos', function (Blueprint $table) {
            $table->dropForeign(['uploader_id']);
        });
        Schema::table('classroom_photos', function (Blueprint $table) {
            $table->foreignId('uploader_id')->nullable()->change();
        });
        Schema::table('classroom_photos', function (Blueprint $table) {
            $table->foreign('uploader_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 削除挙動を cascade に戻す (元の定義)。nullable 化は据え置く
        // (NULL 値が既に入っている可能性があるため必須に戻さない)。
        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('activity_support_plans', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('classroom_photos', function (Blueprint $table) {
            $table->dropForeign(['uploader_id']);
            $table->foreign('uploader_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};

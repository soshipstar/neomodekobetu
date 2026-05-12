<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_chat_messages.original_name を attachment_original_name にリネームする。
 *
 * staff_chat_messages / submission_requests / フロントエンド (page.tsx) は
 * すべて `attachment_original_name` を使用しており、student_chat_messages のみ
 * `original_name` という外れ値の命名だった。さらに Student\ChatController::sendMessage
 * は `attachment_original_name` キーで mass assign しているため、
 * 添付ファイル名が DB に保存されない不具合が発生していた。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_chat_messages', function (Blueprint $table) {
            $table->renameColumn('original_name', 'attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('student_chat_messages', function (Blueprint $table) {
            $table->renameColumn('attachment_original_name', 'original_name');
        });
    }
};

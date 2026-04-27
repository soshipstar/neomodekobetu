<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 代理店ロール用に users.agent_id を追加。
 *
 * 代理店ユーザーは user_type='agent' で識別する。
 * agent_id は所属する代理店の ID。
 *
 * user_type 列はもともと enum ではなく文字列なので、
 * 'agent' を新規値として使う際にDB制約変更は不要。
 * アプリケーション層（CheckUserType middleware など）で許可リストを更新する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('classroom_id')->constrained('agents')->nullOnDelete();
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['agent_id']);
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};

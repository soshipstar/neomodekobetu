<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 各企業の販売チャネル管理用カラムを追加。
 *
 * - agent_id: 代理店経由の場合の代理店ID。NULLなら直販。
 * - commission_rate_override: 企業ごとの手数料率上書き。NULLなら agents.default_commission_rate を使う。
 * - agent_assigned_at: 代理店に紐付いた日（販売チャネル切替日）。
 *   この日以降の Invoice.paid のみ手数料計算対象とする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('feature_flags')->constrained('agents')->nullOnDelete();
            $table->decimal('commission_rate_override', 5, 4)->nullable()->after('agent_id');
            $table->timestamp('agent_assigned_at')->nullable()->after('commission_rate_override');

            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['agent_id']);
            $table->dropForeign(['agent_id']);
            $table->dropColumn(['agent_id', 'commission_rate_override', 'agent_assigned_at']);
        });
    }
};

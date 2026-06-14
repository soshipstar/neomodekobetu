<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 同意の証拠性(rank9): consent_records に同意時の文面スナップショットを保存する。
 *
 * consent_definitions.title/description は版改訂で書き換わりうるため、version だけでは
 * 「過去の同意者が何に同意したか」を後から立証できない(APPI同意の効力・立証責任に不適合)。
 * 同意記録の時点で提示文面(title/description/version/policy_url)を不変スナップショットとして残す。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            $table->jsonb('definition_snapshot')->nullable()->after('version')
                ->comment('同意時点の提示文面の不変スナップショット {title,description,version,policy_url}');
        });

        // 既存行の補完(ベストエフォート): 同版の定義が残っていればその文面を写す。
        // 版は append-only に近い運用のため、当該版のテキストは概ね不変として補完する。
        DB::statement(<<<'SQL'
            UPDATE consent_records cr
            SET definition_snapshot = jsonb_build_object(
                'title', cd.title,
                'description', cd.description,
                'version', cd.version,
                'policy_url', cd.policy_url,
                'backfilled', true
            )
            FROM consent_definitions cd
            WHERE cr.definition_snapshot IS NULL
              AND cr.consent_key = cd.consent_key
              AND cr.version = cd.version
        SQL);
    }

    public function down(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            $table->dropColumn('definition_snapshot');
        });
    }
};

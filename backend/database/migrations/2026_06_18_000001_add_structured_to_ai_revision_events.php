<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 支援知蒸留エンジン D1: 修正イベントに L2 構造化(ルールベース)を付与する。
 *
 * structured(jsonb): タグ(5領域/プログラム/成長段階/コホートの統制コード)と
 * 結果語・仮説語マーカー(本文は保存せずブール+長さのみ=at-rest PII無し)。
 * 事実/結果/仮説の本文分解は D2(AIの問い返し)で職員が埋める前提。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_revision_events', function (Blueprint $table) {
            $table->jsonb('structured')->nullable()
                ->comment('L2構造化(ルール): {tags[], support_category, program_category_id, has_result_marker, has_hypothesis_marker, text_length, method}。本文は含めない');
        });
    }

    public function down(): void
    {
        Schema::table('ai_revision_events', function (Blueprint $table) {
            $table->dropColumn('structured');
        });
    }
};

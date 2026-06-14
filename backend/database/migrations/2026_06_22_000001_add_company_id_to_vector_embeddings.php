<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * テナント分離(rank5): vector_embeddings に company_id を追加する。
 *
 * 現状 company_id 列が無く、類似検索(VectorSearchService)が法人をまたいで全社横断していた
 * (検索は未配線のため顕在化していないが、埋め込みは本番で書き込まれ続けている)。
 * 法人スコープの検索を可能にするため列+索引を追加し、既存行は metadata.classroom_id から補完する。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vector_embeddings', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('source_id')
                ->comment('テナント分離。法人スコープ検索の必須キー');
            $table->index(['company_id', 'source_type'], 'vecemb_company_source_idx');
        });

        // 既存行の補完: metadata.classroom_id → classrooms.company_id。
        // '?' 演算子はPDOのバインド記号と衝突するため使わず、数値判定+キャストで補完する。
        DB::statement(<<<'SQL'
            UPDATE vector_embeddings ve
            SET company_id = c.company_id
            FROM classrooms c
            WHERE ve.company_id IS NULL
              AND (ve.metadata->>'classroom_id') ~ '^[0-9]+$'
              AND c.id = (ve.metadata->>'classroom_id')::bigint
        SQL);
    }

    public function down(): void
    {
        Schema::table('vector_embeddings', function (Blueprint $table) {
            $table->dropIndex('vecemb_company_source_idx');
            $table->dropColumn('company_id');
        });
    }
};

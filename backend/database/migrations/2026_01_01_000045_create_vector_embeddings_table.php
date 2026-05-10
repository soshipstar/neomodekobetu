<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector は PostgreSQL 専用。sqlite でのテスト実行時はスキップする。
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // pgvector拡張を有効化 (拡張がインストールされていない環境ではスキップ)
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            // pgvector が利用できない環境 (テスト用 vanilla postgres など) では
            // ベクトル列 / ivfflat インデックスを作れないため、テーブル作成自体を諦める。
            return;
        }

        Schema::create('vector_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 100)->comment('対象ソース種別');
            $table->unsignedBigInteger('source_id')->comment('対象ソースID');
            $table->text('content_text')->nullable()->comment('埋め込み元テキスト');
            $table->jsonb('metadata')->nullable()->comment('メタデータJSON');
            $table->timestampsTz();

            $table->index(['source_type', 'source_id']);
        });

        // pgvectorのvector型カラムを追加
        DB::statement('ALTER TABLE vector_embeddings ADD COLUMN embedding vector(1536)');

        // ベクトル検索用のインデックス
        DB::statement('CREATE INDEX idx_vector_embeddings_embedding ON vector_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_embeddings');
    }
};

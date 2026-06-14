<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\VectorEmbedding;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function __construct(
        private readonly AiGenerationService $aiService,
    ) {}

    /**
     * Generate an embedding vector from text.
     *
     * @param  string  $text
     * @return array  Float vector
     */
    public function embed(string $text): array
    {
        // Truncate to avoid token limits (roughly 8,000 tokens ~ 16,000 chars for Japanese)
        $truncated = mb_substr($text, 0, 16000);

        return $this->aiService->generateEmbedding($truncated);
    }

    /**
     * Generate an embedding and store it in the vector_embeddings table.
     *
     * @param  string  $sourceType  e.g., 'support_plan', 'monitoring', 'daily_record', 'newsletter'
     * @param  int  $sourceId  The ID of the source record
     * @param  string  $text  The text content to embed
     * @param  array  $metadata  Optional metadata (e.g., student_id, classroom_id)
     * @param  int|null  $companyId  テナント分離キー。未指定なら metadata.classroom_id から補完する。
     * @return VectorEmbedding
     */
    public function storeEmbedding(string $sourceType, int $sourceId, string $text, array $metadata = [], ?int $companyId = null): VectorEmbedding
    {
        $vector = $this->embed($text);
        $vectorString = '[' . implode(',', $vector) . ']';

        // テナント分離(rank5): company_id を必ず確定させる。未指定なら classroom から補完。
        $companyId ??= $this->resolveCompanyId($metadata);
        if ($companyId === null) {
            // 法人が特定できない埋め込みは、法人スコープ検索で「どの法人にも一致しない」=不可視に
            // なる(fail-closed)。漏洩はしないが検索対象から外れるため、検知できるよう警告を残す。
            Log::warning('Embedding stored without company_id (tenant-unscoped)', [
                'source_type' => $sourceType, 'source_id' => $sourceId,
            ]);
        }

        // 観点10 検証可能性 / 観点9 データ品質: 使用した埋め込みモデルと生成時刻を
        // メタデータに記録し、再現性・鮮度管理を可能にする。
        $metadata = array_merge($metadata, [
            'embedding_model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
            'embedded_at' => now()->toIso8601String(),
        ]);

        // Upsert: update existing embedding or create new one
        $embedding = VectorEmbedding::updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'company_id' => $companyId,
                'content_text' => mb_substr($text, 0, 10000),
                'embedding' => $vectorString,
                'metadata' => $metadata,
            ]
        );

        Log::info('Embedding stored', [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'company_id' => $companyId,
            'vector_dimensions' => count($vector),
        ]);

        return $embedding;
    }

    /** metadata.classroom_id から所属法人を解決する(テナント分離キーの補完)。 */
    private function resolveCompanyId(array $metadata): ?int
    {
        $classroomId = $metadata['classroom_id'] ?? null;
        if (! is_numeric($classroomId)) {
            return null;
        }

        return Classroom::whereKey((int) $classroomId)->value('company_id');
    }
}

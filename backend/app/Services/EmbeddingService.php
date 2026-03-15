<?php

namespace App\Services;

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
     * @return VectorEmbedding
     */
    public function storeEmbedding(string $sourceType, int $sourceId, string $text, array $metadata = []): VectorEmbedding
    {
        $vector = $this->embed($text);
        $vectorString = '[' . implode(',', $vector) . ']';

        // Upsert: update existing embedding or create new one
        $embedding = VectorEmbedding::updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'content_text' => mb_substr($text, 0, 10000),
                'embedding' => $vectorString,
                'metadata' => $metadata,
            ]
        );

        Log::info('Embedding stored', [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'vector_dimensions' => count($vector),
        ]);

        return $embedding;
    }
}

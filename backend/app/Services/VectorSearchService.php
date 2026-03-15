<?php

namespace App\Services;

use App\Models\VectorEmbedding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VectorSearchService
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Search for similar content using pgvector cosine distance.
     *
     * @param  string  $query  Natural language query
     * @param  string  $sourceType  Filter by source type (e.g., 'support_plan', 'monitoring', 'daily_record')
     * @param  int  $limit  Maximum results
     * @return Collection  Sorted by similarity (closest first)
     */
    public function search(string $query, string $sourceType, int $limit = 10): Collection
    {
        $queryEmbedding = $this->embeddingService->embed($query);
        $vectorString = $this->vectorToString($queryEmbedding);

        $results = DB::select("
            SELECT
                id,
                source_type,
                source_id,
                content_text,
                metadata,
                (embedding <=> ?::vector) AS distance
            FROM vector_embeddings
            WHERE source_type = ?
            ORDER BY embedding <=> ?::vector
            LIMIT ?
        ", [$vectorString, $sourceType, $vectorString, $limit]);

        return collect($results)->map(function ($row) {
            $row->metadata = json_decode($row->metadata, true);
            $row->similarity = 1 - $row->distance;

            return $row;
        });
    }

    /**
     * Find support plans similar to a given student's existing plans.
     *
     * @param  int  $studentId
     * @return Collection
     */
    public function findSimilarPlans(int $studentId): Collection
    {
        $latestEmbedding = VectorEmbedding::where('source_type', 'support_plan')
            ->whereJsonContains('metadata->student_id', $studentId)
            ->orderByDesc('created_at')
            ->first();

        if (! $latestEmbedding) {
            return collect();
        }

        return DB::table('vector_embeddings')
            ->select('id', 'source_type', 'source_id', 'content_text', 'metadata')
            ->selectRaw('(embedding <=> (SELECT embedding FROM vector_embeddings WHERE id = ?)) AS distance', [$latestEmbedding->id])
            ->where('source_type', 'support_plan')
            ->where('id', '!=', $latestEmbedding->id)
            ->orderBy('distance')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->metadata = json_decode($row->metadata, true);
                $row->similarity = 1 - $row->distance;

                return $row;
            });
    }

    /**
     * Find similar cases based on free-text input with optional filters.
     *
     * @param  string  $text  Text to find similar cases for
     * @param  array  $filters  Optional filters: source_type, classroom_id, min_similarity
     * @return Collection
     */
    public function findSimilarCases(string $text, array $filters = []): Collection
    {
        $queryEmbedding = $this->embeddingService->embed($text);
        $vectorString = $this->vectorToString($queryEmbedding);

        $query = DB::table('vector_embeddings')
            ->select('id', 'source_type', 'source_id', 'content_text', 'metadata')
            ->selectRaw('(embedding <=> ?::vector) AS distance', [$vectorString]);

        if (! empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (! empty($filters['classroom_id'])) {
            $query->whereJsonContains('metadata->classroom_id', $filters['classroom_id']);
        }

        $results = $query
            ->orderBy('distance')
            ->limit($filters['limit'] ?? 20)
            ->get()
            ->map(function ($row) {
                $row->metadata = json_decode($row->metadata, true);
                $row->similarity = 1 - $row->distance;

                return $row;
            });

        // Apply minimum similarity filter if specified
        if (! empty($filters['min_similarity'])) {
            $results = $results->filter(fn ($row) => $row->similarity >= $filters['min_similarity']);
        }

        return $results->values();
    }

    /**
     * Convert a float array to a pgvector-compatible string representation.
     */
    private function vectorToString(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }
}

<?php

namespace Tests\Feature;

use App\Services\AiGenerationService;
use App\Services\EmbeddingService;
use Mockery;
use Tests\TestCase;

/**
 * エラーログ対処: 「Call to undefined method
 * App\Services\AiGenerationService::generateEmbedding()」(error_logs 366件)
 *
 * 差分カテゴリ: logic
 *
 * 原因: EmbeddingService::embed() / VectorSearchService が
 * AiGenerationService::generateEmbedding() を呼んでいたが、当該メソッドが未実装だった。
 * GenerateEmbeddingJob (キュー) が記録作成のたびに失敗し続けていた。
 *
 * 修正: AiGenerationService に generateEmbedding(string): array を実装
 * (OpenAI embeddings API / text-embedding-3-small, 1536次元)。
 *
 * 本テストはネットワークに依存せず、(1) メソッドが存在し正しいシグネチャを持つこと、
 * (2) EmbeddingService::embed() が AiGenerationService::generateEmbedding() に委譲し、
 *     入力を 16,000 文字に切り詰めることを検証する (未定義メソッド再発防止)。
 */
class EmbeddingGenerationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * generateEmbedding メソッドが存在し、文字列を1つ受け取る
     */
    public function test_generate_embedding_method_exists_with_signature(): void
    {
        $this->assertTrue(
            method_exists(AiGenerationService::class, 'generateEmbedding'),
            'AiGenerationService::generateEmbedding() が未実装です。'
        );

        $ref = new \ReflectionMethod(AiGenerationService::class, 'generateEmbedding');
        $this->assertTrue($ref->isPublic());
        $this->assertSame(1, $ref->getNumberOfRequiredParameters());
        $this->assertSame('string', (string) $ref->getParameters()[0]->getType());
    }

    /**
     * EmbeddingService::embed() は AiGenerationService::generateEmbedding() に委譲し、
     * 長文を 16,000 文字に切り詰めて渡す
     */
    public function test_embed_delegates_and_truncates(): void
    {
        $fakeVector = array_fill(0, 1536, 0.01);

        $aiMock = Mockery::mock(AiGenerationService::class);
        $aiMock->shouldReceive('generateEmbedding')
            ->once()
            ->with(Mockery::on(fn ($arg) => is_string($arg) && mb_strlen($arg) <= 16000))
            ->andReturn($fakeVector);

        $service = new EmbeddingService($aiMock);

        $longText = str_repeat('あ', 20000); // 20,000 文字 → 16,000 に切り詰められる想定
        $result = $service->embed($longText);

        $this->assertCount(1536, $result);
        $this->assertSame($fakeVector, $result);
    }
}

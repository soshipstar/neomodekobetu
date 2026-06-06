<?php

namespace Tests\Feature;

use App\Models\AiGenerationLog;
use App\Models\VectorEmbedding;
use App\Services\AiGenerationService;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 観点10 検証可能性: AI生成ログに推論パラメータ・systemプロンプト・参照情報を、
 * ベクトル埋め込みに使用モデル・生成時刻を記録できることの検証。
 *
 * 差分カテゴリ: logic
 */
class AiVerifiabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ai_generation_log_persists_verifiability_fields(): void
    {
        $log = AiGenerationLog::create([
            'generation_type' => 'support_plan',
            'model'           => 'gpt-5.4-2026-03-05',
            'temperature'     => 0.7,
            'max_tokens'      => 4000,
            'system_prompt'   => 'テスト用システムプロンプト',
            'parameters'      => ['response_format' => 'json_object', 'referenced' => ['student_id' => 42], 'finish_reason' => 'stop'],
            'prompt_tokens'   => 100,
            'completion_tokens' => 200,
            'input_data'      => ['prompt' => 'masked prompt'],
            'output_data'     => ['long_term_goal' => 'x'],
            'duration_ms'     => 1234,
        ]);

        $fresh = AiGenerationLog::findOrFail($log->id);

        $this->assertSame(0.7, $fresh->temperature);
        $this->assertSame(4000, $fresh->max_tokens);
        $this->assertSame('テスト用システムプロンプト', $fresh->system_prompt);
        $this->assertIsArray($fresh->parameters);
        $this->assertSame('json_object', $fresh->parameters['response_format']);
        $this->assertSame(42, $fresh->parameters['referenced']['student_id']);
        $this->assertSame('stop', $fresh->parameters['finish_reason']);
    }

    public function test_store_embedding_records_model_and_timestamp_in_metadata(): void
    {
        $fakeVector = array_fill(0, 1536, 0.01);

        $aiMock = Mockery::mock(AiGenerationService::class);
        $aiMock->shouldReceive('generateEmbedding')->once()->andReturn($fakeVector);

        $service = new EmbeddingService($aiMock);
        $service->storeEmbedding('support_plan', 777, '児童の支援テキスト', ['student_id' => 5, 'classroom_id' => 9]);

        $row = VectorEmbedding::where('source_type', 'support_plan')->where('source_id', 777)->firstOrFail();

        $this->assertSame('text-embedding-3-small', $row->metadata['embedding_model']);
        $this->assertArrayHasKey('embedded_at', $row->metadata);
        // 既存メタデータも保持される
        $this->assertSame(5, $row->metadata['student_id']);
        $this->assertSame(9, $row->metadata['classroom_id']);
    }
}

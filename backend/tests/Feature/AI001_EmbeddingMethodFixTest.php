<?php

namespace Tests\Feature;

use App\Services\AiGenerationService;
use Tests\TestCase;

/**
 * AI001: 埋め込みメソッド未実装の修正テスト (AI-07)
 *
 * 差分カテゴリ: logic (AI バグ修正)
 *
 * 放デイ業務リスク監査で検出:
 *  AI-07 EmbeddingService::embed() → AiGenerationService::generateEmbedding()
 *        が未実装で、支援計画承認のたびに BadMethodCallException で必ず失敗。
 *        加えて SupportPlanObserver と SupportPlanService::approvePlan の
 *        両方からジョブがディスパッチされる二重ディスパッチ問題。
 */
class AI001_EmbeddingMethodFixTest extends TestCase
{
    /**
     * generateEmbedding メソッドが AiGenerationService に実装されていること。
     * (旧来は未定義で BadMethodCallException が発生していた)
     */
    public function test_generate_embedding_method_exists(): void
    {
        $this->assertTrue(
            method_exists(AiGenerationService::class, 'generateEmbedding'),
            'AiGenerationService::generateEmbedding() が未実装です (AI-07)。'
        );
    }

    /**
     * 空文字列を渡したら API を呼ばずに空配列を返すこと。
     */
    public function test_generate_embedding_returns_empty_for_blank(): void
    {
        $service = app(AiGenerationService::class);
        $this->assertSame([], $service->generateEmbedding(''));
        $this->assertSame([], $service->generateEmbedding('   '));
    }

    /**
     * SupportPlanService::approvePlan から GenerateEmbeddingJob の直接
     * ディスパッチが除去され、Observer に一本化されていること。
     * (二重ディスパッチ防止 — ソース上で dispatch 呼出が無いことを確認)
     */
    public function test_approve_plan_does_not_double_dispatch_embedding(): void
    {
        $source = file_get_contents(base_path('app/Services/SupportPlanService.php'));

        // approvePlan メソッド本体に GenerateEmbeddingJob::dispatch が
        // (コメント以外で) 残っていないことを確認する。
        // コメント行 (// で始まる) を除いた実コードに dispatch が無いこと。
        $lines = preg_split('/\r\n|\r|\n/', $source);
        $activeDispatch = false;
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//')) {
                continue; // コメント行はスキップ
            }
            if (str_contains($line, 'GenerateEmbeddingJob::dispatch')) {
                $activeDispatch = true;
                break;
            }
        }

        $this->assertFalse(
            $activeDispatch,
            'SupportPlanService に GenerateEmbeddingJob::dispatch が残っています (二重ディスパッチ AI-07)。'
        );
    }
}

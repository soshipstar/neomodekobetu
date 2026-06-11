<?php

namespace Tests\Feature;

use App\Models\MasterAdminAuditLog;
use App\Services\AiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI003: Moderation の fail-warn 化テスト (AI-04)
 *
 * 差分カテゴリ: logic
 *
 * 放デイ業務リスク監査で検出:
 *  AI-04 Moderation API が障害で落ちた時、recordModerationFlag が flagged=false
 *        で何も記録せず素通り (fail-open) していた。有害判定すり抜けと API 障害が
 *        区別できないため、error がある場合も監査ログに記録する fail-warn に変更。
 */
class AI003_ModerationFailWarnTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai04_records_moderation_unavailable_on_error(): void
    {
        // Moderation API が落ちた状態を模した結果 (flagged=false だが error あり)
        $result = [
            'flagged'    => false,
            'categories' => [],
            'scores'     => [],
            'error'      => 'Service Unavailable (503)',
        ];

        AiGenerationService::recordModerationFlag($result, [
            'generation_type' => 'test',
        ]);

        // fail-warn: error がある場合は ai_moderation_unavailable で記録されること
        $log = MasterAdminAuditLog::where('action', 'ai_moderation_unavailable')->first();
        $this->assertNotNull($log, 'Moderation API 障害が監査ログに記録されていません (AI-04 fail-warn)。');
        $this->assertSame('Service Unavailable (503)', $log->context['error'] ?? null);
    }

    public function test_ai04_records_flagged_content(): void
    {
        $result = [
            'flagged'    => true,
            'categories' => ['violence'],
            'scores'     => ['violence' => 0.95],
            'error'      => null,
        ];

        AiGenerationService::recordModerationFlag($result, ['generation_type' => 'test']);

        $log = MasterAdminAuditLog::where('action', 'ai_moderation_flagged')->first();
        $this->assertNotNull($log, '有害判定が監査ログに記録されていません (AI-04)。');
        $this->assertContains('violence', $log->context['categories'] ?? []);
    }

    public function test_ai04_does_not_record_on_clean_success(): void
    {
        // 正常 (flagged=false かつ error なし) は記録しない
        $result = [
            'flagged'    => false,
            'categories' => [],
            'scores'     => [],
            'error'      => null,
        ];

        AiGenerationService::recordModerationFlag($result, ['generation_type' => 'test']);

        $this->assertSame(0, MasterAdminAuditLog::whereIn('action', ['ai_moderation_flagged', 'ai_moderation_unavailable'])->count());
    }
}

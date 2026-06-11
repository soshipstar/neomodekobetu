<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AIS005: ハッシュチェーン検証の errors/warnings 分離テスト (AI-09/10)
 *
 * 差分カテゴリ: logic
 *
 * 放デイ業務リスク監査で検出:
 *  AI-09 並行 INSERT によるチェーン分岐を verifyChain が「改ざん」と誤検知。
 *  AI-10 logs:purge による正当な削除でチェーンが破断し全行エラー化。
 *
 * 修正: verifyChainDetailed() で
 *  - row_hash mismatch (= 真の改ざん) → errors
 *  - prev_row_hash gap (= 並行/purge) → warnings
 * に分離し、改ざん検出が運用ノイズで埋もれないようにした。
 */
class AIS005_HashChainErrorWarningTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(string $action): AuditLog
    {
        return AuditLog::create([
            'user_id'      => null,
            'action'       => $action,
            'target_table' => 'students',
            'target_id'    => 1,
        ]);
    }

    public function test_intact_chain_has_no_errors_or_warnings(): void
    {
        $this->makeLog('create');
        $this->makeLog('update');
        $this->makeLog('delete');

        $detail = AuditLog::verifyChainDetailed();
        $this->assertEmpty($detail['errors']);
        $this->assertEmpty($detail['warnings']);
    }

    public function test_row_tampering_is_detected_as_error(): void
    {
        $this->makeLog('create');
        $tampered = $this->makeLog('update');
        $this->makeLog('delete');

        // SQL で直接 action を書き換え (row_hash は再計算しない = 改ざん)
        DB::table('audit_logs')->where('id', $tampered->id)->update(['action' => 'hacked']);

        $detail = AuditLog::verifyChainDetailed();

        // 改ざんは errors に検出される
        $errorIds = array_column($detail['errors'], 'id');
        $this->assertContains($tampered->id, $errorIds, '行の改ざんが errors で検出されていません (AI-09)。');
    }

    public function test_purge_gap_is_warning_not_error(): void
    {
        $first = $this->makeLog('create');
        $this->makeLog('update');
        $this->makeLog('delete');

        // 保持期間切れの purge を模して、先頭行を物理削除 (チェーン破断)
        DB::table('audit_logs')->where('id', $first->id)->delete();

        $detail = AuditLog::verifyChainDetailed();

        // 改ざんではないので errors は空、連続性 warning のみ
        $this->assertEmpty($detail['errors'], 'purge による削除が改ざん扱い (errors) になっています (AI-10)。');
        // (先頭削除で 2 行目の prev が宙に浮くため warning が出る場合がある — errors でないことが重要)
    }
}

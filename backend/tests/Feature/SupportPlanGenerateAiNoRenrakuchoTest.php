<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 個別支援計画 generateAi は連絡帳を「直接の入力」にしない。
 *
 * 連絡帳の徹底解析は上流のアセスメント生成(直近6か月を全件蒸留)で行い、計画本体は
 * アセスメント(保護者+職員)とモニタリングを土台とする(継続目標=モニタリング/
 * 新規目標=アセスメント)。旧実装は計画生成にも連絡帳直近30件を補足投入していたため、
 * その再投入の回帰を静的に防ぐ(A005 と同様のソース走査方式)。
 *
 * 分類: logic
 */
class SupportPlanGenerateAiNoRenrakuchoTest extends TestCase
{
    private function generateAiBody(): string
    {
        $src = file_get_contents(base_path('app/Http/Controllers/Staff/SupportPlanController.php'));
        $start = strpos($src, 'public function generateAi(');
        $this->assertNotFalse($start, 'generateAi メソッドが見つかりません');
        // 次の public function までを generateAi 本体とみなす(generateAiForStudent 等は除外)
        $next = strpos($src, 'public function ', (int) $start + 20);

        return $next === false ? substr($src, (int) $start) : substr($src, (int) $start, $next - (int) $start);
    }

    public function test_generateAiは連絡帳を直接入力にしない(): void
    {
        $body = $this->generateAiBody();
        $this->assertStringNotContainsString('連絡帳記録（直近', $body,
            '個別支援計画は連絡帳を直接プロンプトへ投入しない設計(アセスメント/モニタリングが土台)');
        $this->assertStringNotContainsString('StudentRecord', $body,
            'generateAi が連絡帳(StudentRecord)を取得しています');
        $this->assertStringNotContainsString('records_period', $body,
            'sources に連絡帳期間キーが残っています');
    }

    public function test_アセスメントとモニタリングは土台として残る(): void
    {
        $body = $this->generateAiBody();
        $this->assertStringContainsString('guardianText', $body);
        $this->assertStringContainsString('staffText', $body);
        $this->assertStringContainsString('monitoringText', $body);
    }

    /**
     * 新規計画 generateAiForStudent は連絡帳が唯一の観察源(アセスメント/モニタリング無し)。
     * 件数で切り捨てず全件を蒸留することを保証する(旧: 最新20件)。
     */
    public function test_generateAiForStudentは連絡帳を全件蒸留する(): void
    {
        $src = file_get_contents(base_path('app/Http/Controllers/Staff/SupportPlanController.php'));
        $start = strpos($src, 'public function generateAiForStudent(');
        $this->assertNotFalse($start, 'generateAiForStudent が見つかりません');
        $next = strpos($src, 'public function ', (int) $start + 30);
        $body = $next === false ? substr($src, (int) $start) : substr($src, (int) $start, $next - (int) $start);

        $this->assertStringContainsString('RecordDistillationService', $body,
            '新規計画生成が蒸留サービスを使っていません');
        $this->assertStringContainsString('distillByDomain', $body);
        $this->assertStringNotContainsString('->limit(20)', $body,
            '連絡帳が最新20件に切り捨てられています(取りこぼし)');
    }
}

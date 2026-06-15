<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Services\RecordDistillationService;
use App\Support\PiiMasker;
use Tests\TestCase;

/**
 * RecordDistillationService: 連絡帳の「全件」二段集約(蒸留)が、
 * 件数上限による取りこぼし("死んでいる連絡帳")を起こさないことを検証する。
 *
 * 旧実装は職員アセスメント=領域別最新10件 / モニタリング=目標別最新20件で切り捨てていた。
 * 本サービスは全レコードを必ずいずれかのチャンクで要約対象に通すことを保証する。
 *
 * 分類: logic
 */
class RecordDistillationServiceTest extends TestCase
{
    /** dailyRecord と5領域カラムを持つ StudentRecord 互換のダミーを作る(DB不要)。 */
    private function fakeRecord(string $date, array $domains): object
    {
        $r = new \stdClass();
        $r->dailyRecord = (object) ['record_date' => $date];
        foreach (array_keys(RecordDistillationService::DOMAINS) as $col) {
            $r->{$col} = $domains[$col] ?? null;
        }
        $r->notes = null;

        return $r;
    }

    private function passthroughMasker(): PiiMasker
    {
        $masker = $this->createMock(PiiMasker::class);
        $masker->method('mask')->willReturnCallback(fn ($s) => $s);

        return $masker;
    }

    public function test_全件がいずれかのチャンクで要約され取りこぼしが無い(): void
    {
        // 120件(chunkSize 40 → 3チャンク)。各件に一意マーカーを付与。
        $records = [];
        for ($i = 0; $i < 120; $i++) {
            $day = str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT);
            $records[] = $this->fakeRecord("2026-01-{$day}", ['health_life' => "MARK{$i}-健康観察"]);
        }

        $seen = [];
        $summarizer = function (string $maskedPrompt) use (&$seen): array {
            if (preg_match_all('/MARK(\d+)-/', $maskedPrompt, $m)) {
                foreach ($m[1] as $n) {
                    $seen[(int) $n] = true;
                }
            }

            return ['{"health_life":"チャンク要約"}', 10, 5];
        };

        $svc = new RecordDistillationService();
        $res = $svc->distillByDomain(
            $this->createMock(Student::class),
            $records,
            $this->passthroughMasker(),
            $summarizer,
            chunkSize: 40,
            rawThreshold: 24,
        );

        // 全120件が要約器に渡った = 取りこぼしゼロ
        $this->assertCount(120, $seen, '一部の連絡帳が要約対象から漏れている(死んでいる連絡帳)');
        for ($i = 0; $i < 120; $i++) {
            $this->assertArrayHasKey($i, $seen, "record {$i} が要約に渡っていない");
        }

        $this->assertSame(120, $res['source_count']);
        $this->assertSame(120, $res['counts']['health_life']);
        $this->assertSame(3, $res['chunks'], '120件は40件×3チャンクに分割されるはず');
        $this->assertNotSame('', $res['digests']['health_life']);
        $this->assertSame(30, $res['input_tokens']); // 10 × 3チャンク
    }

    public function test_小規模はAI要約を呼ばず全文をそのまま使う(): void
    {
        $records = [
            $this->fakeRecord('2026-01-01', ['health_life' => '朝の支度を自分で行えた']),
            $this->fakeRecord('2026-01-02', ['motor_sensory' => '階段の上り下りが安定']),
        ];

        $called = false;
        $summarizer = function () use (&$called): array {
            $called = true;

            return ['{}', 0, 0];
        };

        $svc = new RecordDistillationService();
        $res = $svc->distillByDomain(
            $this->createMock(Student::class),
            $records,
            $this->passthroughMasker(),
            $summarizer,
        );

        $this->assertFalse($called, '小規模データで不要なAI要約が呼ばれている');
        $this->assertSame(0, $res['chunks']);
        $this->assertSame(2, $res['source_count']);
        $this->assertStringContainsString('朝の支度を自分で行えた', $res['digests']['health_life']);
        $this->assertStringContainsString('階段の上り下りが安定', $res['digests']['motor_sensory']);
    }

    public function test_要約失敗時は生記録を残して取りこぼさない(): void
    {
        $records = [];
        for ($i = 0; $i < 30; $i++) {
            $records[] = $this->fakeRecord('2026-02-01', ['cognitive_behavior' => "DROP{$i}-認知"]);
        }

        // 不正JSONを返す要約器 → フォールバックで生記録が残るはず
        $svc = new RecordDistillationService();
        $res = $svc->distillByDomain(
            $this->createMock(Student::class),
            $records,
            $this->passthroughMasker(),
            fn (string $p): array => ['not-json', 1, 1],
            chunkSize: 40,
            rawThreshold: 24,
        );

        for ($i = 0; $i < 30; $i++) {
            $this->assertStringContainsString("DROP{$i}-認知", $res['digests']['cognitive_behavior'],
                "要約失敗時に record {$i} が失われた");
        }
    }

    public function test_toPromptTextは指定領域のみ出力する(): void
    {
        $svc = new RecordDistillationService();
        $digests = [
            'health_life' => '健康の要約',
            'motor_sensory' => '運動の要約',
            'cognitive_behavior' => '',
            'language_communication' => '言語の要約',
            'social_relations' => '',
        ];

        $only = $svc->toPromptText($digests, ['motor_sensory']);
        $this->assertStringContainsString('運動の要約', $only);
        $this->assertStringNotContainsString('健康の要約', $only);
        $this->assertStringNotContainsString('言語の要約', $only);
    }
}

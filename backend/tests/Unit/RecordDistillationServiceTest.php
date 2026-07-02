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

    public function test_月別グループ化はチャンクが月をまたがず月ラベル付きで時系列順になる(): void
    {
        // 1月30件 + 2月30件 (chunkSize 40)。月別でなければ 40+20 の2チャンクで月が混ざる。
        // 月別なら「1月30件」「2月30件」の2チャンクに分かれ、各チャンクは単一月のみを含む。
        $records = [];
        for ($i = 0; $i < 30; $i++) {
            $day = str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT);
            $records[] = $this->fakeRecord("2026-02-{$day}", ['health_life' => "FEB{$i}-健康"]);
        }
        for ($i = 0; $i < 30; $i++) {
            $day = str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT);
            $records[] = $this->fakeRecord("2026-01-{$day}", ['health_life' => "JAN{$i}-健康"]);
        }
        // わざと新しい月(2月)を先に渡す → サービス側で古い月→新しい月に整列されるはず

        $prompts = [];
        $seen = [];
        $summarizer = function (string $maskedPrompt) use (&$prompts, &$seen): array {
            $prompts[] = $maskedPrompt;
            if (preg_match_all('/(JAN|FEB)(\d+)-/', $maskedPrompt, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $seen[$hit[1] . $hit[2]] = true;
                }
            }

            return ['{"health_life":"月チャンク要約"}', 10, 5];
        };

        $svc = new RecordDistillationService();
        $res = $svc->distillByDomain(
            $this->createMock(Student::class),
            $records,
            $this->passthroughMasker(),
            $summarizer,
            chunkSize: 40,
            rawThreshold: 24,
            groupByMonth: true,
        );

        // 取りこぼしゼロは月別でも維持
        $this->assertCount(60, $seen, '月別グループ化で連絡帳の取りこぼしが発生');

        // 月をまたぐチャンクが無い(各プロンプトは単一月の日付のみを含む)
        $this->assertSame(2, $res['chunks'], '1月30件/2月30件は月別に2チャンクになるはず');
        foreach ($prompts as $p) {
            $hasJan = str_contains($p, '2026-01');
            $hasFeb = str_contains($p, '2026-02');
            $this->assertFalse($hasJan && $hasFeb, 'チャンクが月をまたいでいる(時間構造が壊れる)');
        }

        // 要約に月ラベルが付き、古い月→新しい月の順に並ぶ
        $digest = $res['digests']['health_life'];
        $this->assertStringContainsString('【2026年1月】', $digest);
        $this->assertStringContainsString('【2026年2月】', $digest);
        $this->assertLessThan(
            strpos($digest, '【2026年2月】'),
            strpos($digest, '【2026年1月】'),
            '月ラベルが時系列順(古い→新しい)になっていない'
        );
    }

    public function test_月別グループ化の小規模は生データが時系列順に並ぶ(): void
    {
        // rawThreshold 以下: AI要約なしで全文。新しい日付を先に渡しても古い順に整列される。
        $records = [
            $this->fakeRecord('2026-06-10', ['health_life' => '6月の記録']),
            $this->fakeRecord('2026-01-05', ['health_life' => '1月の記録']),
        ];

        $called = false;
        $svc = new RecordDistillationService();
        $res = $svc->distillByDomain(
            $this->createMock(Student::class),
            $records,
            $this->passthroughMasker(),
            function () use (&$called): array {
                $called = true;

                return ['{}', 0, 0];
            },
            groupByMonth: true,
        );

        $this->assertFalse($called);
        $digest = $res['digests']['health_life'];
        $this->assertLessThan(
            strpos($digest, '6月の記録'),
            strpos($digest, '1月の記録'),
            '小規模(生データ)でも古い→新しいの時系列順になるべき'
        );
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

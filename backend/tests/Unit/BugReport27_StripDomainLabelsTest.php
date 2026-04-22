<?php

namespace Tests\Unit;

use App\Http\Controllers\Staff\RenrakuchoController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * バグ報告 #27: 連絡帳統合時に統合前の【健康・生活】等の領域ラベルが残る
 *
 * 差分カテゴリ: logic
 * 背景: プロンプトに与えた 5 領域ラベル（【健康・生活】等）が AI 出力に
 *       そのまま残ることがあった。フォールバック結合では必ず残っていた。
 *       stripDomainLabels() で 5 領域ラベルのみホワイトリスト除去する。
 */
class BugReport27_StripDomainLabelsTest extends TestCase
{
    private function invoke(string $text): string
    {
        $controller = new RenrakuchoController();
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('stripDomainLabels');
        $method->setAccessible(true);
        return $method->invoke($controller, $text);
    }

    public function test_strips_all_five_domain_labels(): void
    {
        $input = '【健康・生活】朝の準備【運動・感覚】走った【認知・行動】考えた【言語・コミュニケーション】話した【人間関係・社会性】仲良くした';
        $expected = '朝の準備走った考えた話した仲良くした';
        $this->assertSame($expected, $this->invoke($input));
    }

    public function test_keeps_other_brackets(): void
    {
        // 5 領域以外の【】は温存する
        $input = '本日は【気になった点】があります。【次のステップ】を提案します。';
        $this->assertSame($input, $this->invoke($input));
    }

    public function test_handles_missing_labels(): void
    {
        $input = '通常のテキストです。';
        $this->assertSame($input, $this->invoke($input));
    }

    public function test_strips_mixed_content(): void
    {
        $input = '本日は元気に過ごしました。【健康・生活】お昼ごはんを完食しました。【気になった点】少し眠そうでした。';
        $expected = '本日は元気に過ごしました。お昼ごはんを完食しました。【気になった点】少し眠そうでした。';
        $this->assertSame($expected, $this->invoke($input));
    }
}

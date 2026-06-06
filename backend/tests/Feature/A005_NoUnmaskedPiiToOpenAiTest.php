<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A-005: 外部AI(OpenAI)を直接呼び出す全ての箇所が PiiMasker を経由していること。
 *
 * 観点5 プライバシー保護 / 優先度 P0。
 * これまで AiGenerationService 経由分はマスク済だったが、各 Staff コントローラが
 * インラインで `\OpenAI::client()->chat()->create()` を呼び、児童の実名を無加工で
 * 送信していた漏れが複数回発生した (commit 0277910 では Meeting/Renrakucho が漏れた)。
 *
 * この静的ガードは、`->chat()->create(` を含むファイルは必ず PiiMasker を参照する
 * (= マスキング層を通している) ことを強制し、新たなインライン経路が無加工で
 * PII を外部送信する回帰を防ぐ。
 *
 * PII を一切含まない (児童・保護者の実名を渡さない) ことが監査で確認できた経路のみ
 * ALLOWLIST で除外する。新規に AI 呼び出しを追加する場合は、PiiMasker を適用するか、
 * PII を含まないことを確認した上でこの ALLOWLIST に追記すること。
 *
 * 差分カテゴリ: logic (外部AIへのPII送信防止)
 */
class A005_NoUnmaskedPiiToOpenAiTest extends TestCase
{
    /**
     * PII (児童・保護者の実名) をプロンプトに含まないことを監査済みのファイル。
     * これらは PiiMasker が無くてもよい。
     */
    private const ALLOWLIST = [
        // お便り: 教室名・活動名のみで個人名を含まない
        'NewsletterController.php',
        // 活動支援案: 活動名・目的・対象学年のみで個人名を含まない
        'ActivitySupportPlanAiService.php',
        // ふりがな生成バッチ: 氏名そのものが生成対象のためマスク不可 (管理者専用バッチ)
        'BackfillKana.php',
    ];

    public function test_every_openai_call_site_uses_pii_masker(): void
    {
        $appDir = base_path('app');
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            // OpenAI のチャット呼び出しを含むか
            if (! str_contains($contents, '->chat()->create')) {
                continue;
            }

            // 監査済みで PII を含まない経路は除外
            if (in_array($file->getFilename(), self::ALLOWLIST, true)) {
                continue;
            }

            // PiiMasker を参照していなければ PII 無加工送信の疑い
            if (! str_contains($contents, 'PiiMasker')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "以下のファイルは OpenAI を呼び出していますが PiiMasker を経由していません" .
            " (児童実名の無加工送信の疑い)。マスキングを適用するか、PII を含まないことを" .
            "確認の上 ALLOWLIST に追加してください:\n" . implode("\n", $offenders)
        );
    }
}

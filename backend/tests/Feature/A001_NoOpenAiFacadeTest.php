<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A-001: OpenAI\Laravel\Facades\OpenAI が一切使用されていないことを検証
 *
 * openai-php/laravel パッケージは未インストールのため、
 * `use OpenAI\Laravel\Facades\OpenAI` や Facade 経由の `OpenAI::` 呼び出しは
 * 全て `\OpenAI::client($apiKey)` に置き換え済みであること。
 */
class A001_NoOpenAiFacadeTest extends TestCase
{
    /**
     * app/ 配下に OpenAI\Laravel\Facades\OpenAI の use 文が残っていないこと
     */
    public function test_no_openai_laravel_facade_import(): void
    {
        $appDir = base_path('app');
        $pattern = 'OpenAI\\Laravel\\Facades\\OpenAI';

        $matches = $this->grepRecursive($appDir, $pattern);

        $this->assertCount(
            0,
            $matches,
            "以下のファイルに OpenAI\\Laravel\\Facades\\OpenAI が残っています:\n" . implode("\n", $matches)
        );
    }

    /**
     * app/ 配下に `use OpenAI;` (bare facade alias) が残っていないこと
     *
     * 正規のクライアント呼び出しは `\OpenAI::client(...)` (完全修飾) を使用するので
     * `use OpenAI;` は不要。
     */
    public function test_no_bare_openai_use_statement(): void
    {
        $appDir = base_path('app');
        // match "use OpenAI;" exactly (not "use OpenAI\Something")
        $pattern = '/^use\s+OpenAI\s*;/m';

        $matches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (preg_match($pattern, $contents)) {
                $matches[] = $file->getPathname();
            }
        }

        $this->assertCount(
            0,
            $matches,
            "以下のファイルに `use OpenAI;` が残っています:\n" . implode("\n", $matches)
        );
    }

    /**
     * 再帰 grep ヘルパー
     */
    private function grepRecursive(string $dir, string $needle): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (str_contains($contents, $needle)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }
}

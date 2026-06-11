<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * 監査ログ / AI 生成ログ用のハッシュチェーン機能。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R9 (2026-05-17):
 *  - 各行が直前行の row_hash を含めた sha256 を保持することで、
 *    後から行を改ざんした場合に整合性破綻が検出可能になる。
 *  - モデルの creating イベントで自動計算 → row_hash / prev_row_hash カラムを設定。
 *
 * 使い方:
 *  - モデルクラスで `use HashChainable;` を宣言
 *  - モデルに `protected array $hashFields = [...]` を定義し、ハッシュ対象のキー名を列挙
 *    (例: ['user_id', 'action', 'target_table', 'target_id', 'old_values', 'new_values'])
 *  - 既存行への backfill は `php artisan audit-logs:backfill-hash` で行う (別途実装)
 *
 * 制約:
 *  - 並行 INSERT 時のチェーン分岐は避けるため、creating 時に最新行を取得して
 *    prev_row_hash に設定する。これは worst-case で同時 INSERT 2 件が同じ
 *    prev を参照する可能性があるが、その場合は両方が verifyChain で検出される。
 *    検出後の修復はマスター管理者の運用作業とする。
 */
trait HashChainable
{
    /**
     * モデル boot 時にイベントを登録。
     * 各モデルは `$hashFields` プロパティを必ず定義すること。
     */
    public static function bootHashChainable(): void
    {
        static::creating(function (Model $model) {
            /** @var array<int, string> $fields */
            $fields = property_exists($model, 'hashFields') ? $model->hashFields : [];

            // 直前行の row_hash を取得
            $prev = static::query()
                ->orderByDesc('id')
                ->limit(1)
                ->value('row_hash');

            $model->prev_row_hash = $prev ?: null;
            $model->row_hash = self::computeHash($model, $fields, $prev);
        });
    }

    /**
     * 行の row_hash を計算する。
     *
     * @param array<int, string> $fields
     */
    public static function computeHash(Model $model, array $fields, ?string $prevHash): string
    {
        $payload = ['prev' => $prevHash ?? ''];
        foreach ($fields as $f) {
            $payload[$f] = $model->getAttribute($f);
        }
        // 配列/オブジェクト→JSON で安定化、整数等はそのまま
        $serialized = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return hash('sha256', $serialized);
    }

    /**
     * チェーン全体を検証する。
     *
     * AI-09/10 修正: 2 種類の不整合を errors / warnings に分離する。
     *  - errors   : row_hash mismatch = 行自体の改ざん。prev_row_hash を含めて
     *               再計算するため、真の改ざんは確実にここで検出される (偽陽性なし)。
     *  - warnings : prev_row_hash mismatch = チェーンの連続性ギャップ。並行 INSERT に
     *               よるチェーン分岐 (AI-09) や、保持期間切れの logs:purge による
     *               正当な削除 (AI-10) でも発生するため、改ざんとは区別して警告扱いに
     *               する。これにより「真の改ざん検出」が運用ノイズで埋もれない。
     *
     * 後方互換のため、戻り値は従来の「errors 配列」をそのまま返す。
     * warnings も取得したい場合は verifyChainDetailed() を使う。
     *
     * @return array<int, array{id: int, error: string}>
     */
    public static function verifyChain(int $limit = 100000): array
    {
        return self::verifyChainDetailed($limit)['errors'];
    }

    /**
     * チェーン検証の詳細版。errors と warnings を分離して返す。
     *
     * @return array{errors: array<int, array{id: int, error: string}>, warnings: array<int, array{id: int, error: string}>}
     */
    public static function verifyChainDetailed(int $limit = 100000): array
    {
        $errors = [];
        $warnings = [];
        $expectedPrev = null;
        $count = 0;

        static::query()
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$errors, &$warnings, &$expectedPrev, &$count, $limit) {
                foreach ($rows as $row) {
                    $count++;
                    if ($count > $limit) return false;

                    // 連続性ギャップ (並行 INSERT 分岐 / purge による削除) = 警告
                    if ($expectedPrev !== null && $row->prev_row_hash !== $expectedPrev) {
                        $warnings[] = [
                            'id'    => $row->id,
                            'error' => "prev_row_hash gap (expected {$expectedPrev}, got {$row->prev_row_hash}) — 並行INSERT分岐またはpurgeによる正当な削除の可能性",
                        ];
                    }

                    /** @var array<int, string> $fields */
                    $fields = property_exists($row, 'hashFields') ? $row->hashFields : [];
                    $recomputed = self::computeHash($row, $fields, $row->prev_row_hash);

                    // 行自体の改ざん = エラー (本質的な改ざん検出)
                    if ($recomputed !== $row->row_hash) {
                        $errors[] = [
                            'id'    => $row->id,
                            'error' => "row_hash mismatch (expected {$recomputed}, got {$row->row_hash}) — 行の改ざんの疑い",
                        ];
                    }

                    $expectedPrev = $row->row_hash;
                }
                return true;
            });

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}

<?php

namespace App\Services;

/**
 * LLM プロンプトに含める個人識別情報の仮名化 / 復元ヘルパー。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R2 (2026-05-17):
 *  - V5 プライバシー保護 / 表 3-6 ③ 入力時の個人情報マスキング・仮名化
 *  - 4.2.5 (1) プライバシー・バイ・デザイン: LLM 入力データの個人情報最小化
 *  - 個情法 28 条 (外国にある第三者への提供) における安全管理措置の補強
 *
 * 設計:
 *  - 1 リクエスト = 1 インスタンス。プロンプト構築時に対象固有名詞 (児童名、教室名、
 *    保護者名、職員名) を `mask()` で placeholder (例: 「対象児童 A」) に置換し、
 *    内部マップに記録。
 *  - 同じ実名は同じ placeholder にマップ (会話内一貫性)。
 *  - AI 応答取得後、`unmask()` で placeholder を実名に戻す (= 業務記録としての可読性確保)。
 *  - `AiGenerationLog` 等への保存は **マスク後の文字列** とすることで、
 *    ログにも実名が残らない設計。
 *
 * 仮名化対象 (直接識別子):
 *  - 児童氏名 (student_name)
 *  - 保護者氏名 (full_name)
 *  - 職員氏名 (full_name)
 *  - 教室名 / 事業所名 (classroom_name)
 *
 * 仮名化しない (文脈理解に必要 + 単独で識別性なし):
 *  - 学年、サービス種別、障害種別の大分類、日付
 */
class AiIdentityMasker
{
    /** @var array<string, string> 実名 => placeholder */
    private array $map = [];

    /** @var array<string, int> placeholder 種別ごとのカウンタ */
    private array $counters = [
        'student'   => 0,
        'guardian'  => 0,
        'staff'     => 0,
        'classroom' => 0,
        'other'     => 0,
    ];

    /** placeholder 種別ごとの和文ラベル */
    private const LABELS = [
        'student'   => '対象児童',
        'guardian'  => '保護者',
        'staff'     => '職員',
        'classroom' => '事業所',
        'other'     => '関係者',
    ];

    /**
     * 単一の実名を placeholder に登録。既に登録済みなら既存の placeholder を返す。
     *
     * @param string $real     実名 (例: "山田 太郎")
     * @param string $category student | guardian | staff | classroom | other
     */
    public function register(string $real, string $category = 'other'): string
    {
        $real = trim($real);
        if ($real === '') return '';

        if (isset($this->map[$real])) {
            return $this->map[$real];
        }

        $category = isset(self::LABELS[$category]) ? $category : 'other';
        $n = ++$this->counters[$category];

        $label = self::LABELS[$category];
        $placeholder = $n <= 26
            ? "{$label} " . chr(0x40 + $n)            // 「対象児童 A」
            : "{$label} " . $n;                        // 「対象児童 27」

        $this->map[$real] = $placeholder;
        return $placeholder;
    }

    /**
     * 複数の実名を一括登録。
     */
    public function registerAll(array $names): void
    {
        foreach ($names as $category => $list) {
            $list = is_array($list) ? $list : [$list];
            foreach ($list as $real) {
                if (! empty($real)) {
                    $this->register((string) $real, (string) $category);
                }
            }
        }
    }

    /**
     * 文字列内の全実名を placeholder に置換して返す。
     * (register 済みの実名のみ対象 — 未登録は素通し)
     */
    public function mask(string $text): string
    {
        // 長い名前から先に置換 (部分一致による誤置換を防ぐ)
        $names = array_keys($this->map);
        usort($names, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($names as $real) {
            $text = str_replace($real, $this->map[$real], $text);
        }
        return $text;
    }

    /**
     * AI 応答テキスト中の placeholder を実名に戻す。
     */
    public function unmask(string $output): string
    {
        // 長い placeholder から先に置換
        $placeholders = array_values($this->map);
        usort($placeholders, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        $reverse = array_flip($this->map);
        foreach ($placeholders as $ph) {
            if (isset($reverse[$ph])) {
                $output = str_replace($ph, $reverse[$ph], $output);
            }
        }
        return $output;
    }

    /**
     * 指定の実名に対する placeholder を取得 (なければ空文字)。
     */
    public function placeholderFor(string $real): string
    {
        return $this->map[trim($real)] ?? '';
    }

    /**
     * マップ全体を返す (監査用 / テスト用)。
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * マップに含まれる実名が出力に残っていないか後置検査。
     * unmask 漏れ / placeholder 形式違いの検出に使う。
     *
     * @return string[] 出力に placeholder 形式 (「対象児童 A」等) が残っていた場合のリスト
     */
    public function detectPlaceholderLeakage(string $output): array
    {
        $hits = [];
        foreach ($this->map as $ph) {
            if (mb_stripos($output, $ph) !== false) {
                $hits[] = $ph;
            }
        }
        return array_values(array_unique($hits));
    }
}

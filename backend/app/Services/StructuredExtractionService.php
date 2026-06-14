<?php

namespace App\Services;

/**
 * 支援知蒸留エンジン D1: L2 構造化(ルールベース)。
 *
 * 人間の最終稿(after_text)から、外部AIを使わずに以下を抽出する:
 *  - tags: 5領域 / 実施プログラム / 成長段階 / コホート の統制コード(PIIなし)
 *  - 結果語・仮説語マーカー(本文は保存せず含有有無のみ。at-rest PII を出さない)
 *
 * 事実/支援/結果/仮説の本文分解は D2(AIの問い返し)で職員が埋める前提。
 * 結果/仮説マーカーは D3(支援者成長モデル: 仮説を書けているか)の判定材料にもなる。
 */
class StructuredExtractionService
{
    /** 結果を示す語(「〜できた」「参加」「増減」「落ち着いた」等)。 */
    public const RESULT_MARKERS = [
        'できた', 'できるように', '参加', '増え', '減っ', '落ち着', '成功', '達成', '向上', '改善', '自分から', '自発', 'なった',
    ];

    /** 因果仮説を示す語(「〜のため」「原因」「思われる」等)。 */
    public const HYPOTHESIS_MARKERS = [
        'ため', 'から', '原因', '思われ', 'と考え', '可能性', '背景', '理由', 'につなが', 'によって', 'おかげ',
    ];

    /**
     * @param  array<int,string>  $extraTags
     * @return array<string,mixed>
     */
    public static function extract(
        string $afterText,
        ?string $supportCategory,
        ?int $programCategoryId,
        ?string $cohort,
        ?string $growthStage,
        array $extraTags = [],
    ): array {
        $tags = array_values(array_filter(array_merge([
            $supportCategory, $cohort, $growthStage,
        ], $extraTags), fn ($v) => $v !== null && $v !== ''));
        if ($programCategoryId) {
            $tags[] = 'program:'.$programCategoryId;
        }

        return [
            'tags' => array_values(array_unique($tags)),
            'support_category' => $supportCategory,
            'program_category_id' => $programCategoryId,
            'has_result_marker' => self::containsAny($afterText, self::RESULT_MARKERS),
            'has_hypothesis_marker' => self::containsAny($afterText, self::HYPOTHESIS_MARKERS),
            'text_length' => mb_strlen($afterText),
            'method' => 'rule',
        ];
    }

    /** @param  array<int,string>  $markers */
    private static function containsAny(string $text, array $markers): bool
    {
        if ($text === '') {
            return false;
        }
        foreach ($markers as $m) {
            if (mb_strpos($text, $m) !== false) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Support;

use App\Models\Student;

/**
 * AI学習基盤 S4e: 児童の「特性」統制語彙(多次元分析の軸)。
 *
 * 方針(S4計画 §6/§8.2):
 *  - 診断名・医療情報は扱わない。あくまで「支援上の特性」カテゴリに限定する。
 *  - 自由記述PIIは禁止。固定の統制コードのみ(集計可能・実名混入なし)。
 *  - 要配慮。集計のみに使用(同意済み・k匿名)。プロンプトには既定で入れない。
 *
 * コホート([[StudentCohort]])・成長段階([[AbilityGrowthStage]])と同じく固定の統制軸。
 */
class StudentTrait
{
    /** code => 日本語ラベル。順序は表示・正規化の決定的順序を兼ねる。 */
    public const TAGS = [
        'sensory_sensitive' => '感覚過敏',
        'sensory_seeking' => '感覚探求',
        'attention_short' => '注意の持続が難しい',
        'hyperactive' => '多動・落ち着きにくい',
        'impulsive' => '衝動性',
        'transition_hard' => '切り替え・変更が苦手',
        'routine_strong' => 'こだわりが強い',
        'communication_support' => 'コミュニケーション支援',
        'language_support' => 'ことば・発語の支援',
        'social_support' => '対人・集団が苦手',
        'anxiety_high' => '不安・緊張が高い',
        'emotion_regulation' => '感情の調整が難しい',
        'learning_support' => '学習・理解の支援',
        'motor_support' => '運動・協調の支援',
        'self_care_support' => '身辺自立の支援',
        'strength_focus' => '強み・才能への配慮',
    ];

    /** 有効なコード一覧。 */
    public static function codes(): array
    {
        return array_keys(self::TAGS);
    }

    /**
     * UI/API 用の語彙([{code,label}])。
     *
     * @return array<int,array{code:string,label:string}>
     */
    public static function vocabulary(): array
    {
        $out = [];
        foreach (self::TAGS as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }

    /**
     * 入力を有効コードのみへ正規化する。未知コード・自由記述・重複は捨てる(PII/ノイズ防止)。
     * 出力は語彙順に整列した決定的な配列(差分・突き合わせを安定させる)。
     *
     * @return array<int,string>
     */
    public static function sanitize(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }
        $selected = [];
        foreach ($input as $v) {
            if (is_string($v) && isset(self::TAGS[$v])) {
                $selected[$v] = true;
            }
        }

        return array_values(array_filter(self::codes(), fn ($code) => isset($selected[$code])));
    }

    /**
     * 児童の特性コード配列(正規化済)。
     *
     * @return array<int,string>
     */
    public static function forStudent(Student $student): array
    {
        return self::sanitize($student->traits);
    }

    /**
     * コード配列 → ラベル配列(正規化込み)。
     *
     * @return array<int,string>
     */
    public static function labelsFor(mixed $codes): array
    {
        return array_map(fn ($code) => self::TAGS[$code], self::sanitize($codes));
    }
}

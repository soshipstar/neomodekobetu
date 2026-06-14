<?php

namespace App\Services;

use App\Models\AiRevisionEvent;

/**
 * 支援知蒸留 D3: 支援者成長モデル。
 *
 * 職員の記録の傾向(D1の因果マーカー・見本採用歴)から Lv1〜4 を判定し、
 * レベルに応じて AI 支援の強度を出し分ける(= 新人ほど足場多め、熟達ほど最小限)。
 * 育成目的の指標であり、修正「回数」での評価には使わない(成長志向)。
 *
 *  Lv1 新人  : 事実のみ記録      → 観察を引き出す問いを多めに
 *  Lv2 初級  : 状況/結果を書ける  → 結果と要因をつなぐ問い(因果思考)
 *  Lv3 中堅  : 仮説を書ける       → 記述の不足を補う添削視点
 *  Lv4 ベテラン: 支援設計できる    → 問いは最小限、代替仮説・別観点を提示
 */
class SupporterLevelService
{
    public const LABELS = [1 => '新人', 2 => '初級', 3 => '中堅', 4 => 'ベテラン'];

    private const NEXT_HINT = [
        1 => 'まずは「どの場面で・何が違ったか」を具体的に書く習慣を。',
        2 => '「なぜそうなったか(要因・仮説)」まで一言添えてみましょう。',
        3 => '仮説と支援設計のつながりを意識すると、より実践的な記録に。',
        4 => '記録が見本として他の職員の学びにつながっています。',
    ];

    /**
     * 職員の現在レベルと判定根拠を返す。
     *
     * @return array{level:int,label:string,signals:array<string,mixed>,next_hint:string}
     */
    public function levelFor(int $userId, ?int $companyId = null): array
    {
        $events = AiRevisionEvent::where('editor_user_id', $userId)
            ->where('changed', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id')->limit(100)
            ->get(['structured', 'exemplar_status']);

        $n = $events->count();
        if ($n < 3) {
            return $this->result(1, ['records' => $n]);
        }

        $hyp = $events->filter(fn ($r) => ($r->structured['has_hypothesis_marker'] ?? false))->count();
        $res = $events->filter(fn ($r) => ($r->structured['has_result_marker'] ?? false))->count();
        $adopted = $events->filter(fn ($r) => $r->exemplar_status === 'adopted')->count();
        $hypRate = $hyp / $n;
        $resRate = $res / $n;

        $level = match (true) {
            $hypRate >= 0.5 && $adopted >= 1 => 4,
            $hypRate >= 0.4 => 3,
            $resRate >= 0.4 => 2,
            default => 1,
        };

        return $this->result($level, [
            'records' => $n,
            'hypothesis_rate' => round($hypRate, 2),
            'result_rate' => round($resRate, 2),
            'adopted_exemplars' => $adopted,
        ]);
    }

    /**
     * レベルに応じた問い返し(D2)の方針。
     *
     * @return array{question_count:int,style:string}
     */
    public function inquiryPolicy(int $level): array
    {
        return match ($level) {
            1 => ['question_count' => 5, 'style' => '観察を引き出す基本的な問いを多めに出し、事実・場面・支援を具体化させる。'],
            2 => ['question_count' => 4, 'style' => '結果と要因をつなぐ問いを促し、因果で考えられるよう導く。'],
            3 => ['question_count' => 3, 'style' => '記述の不足を補う添削視点の問いを中心にする。'],
            4 => ['question_count' => 2, 'style' => '問いは最小限にし、代替仮説や別の観点の提示に重きを置く。'],
            default => ['question_count' => 4, 'style' => ''],
        };
    }

    /** @param  array<string,mixed>  $signals */
    private function result(int $level, array $signals): array
    {
        return [
            'level' => $level,
            'label' => self::LABELS[$level],
            'signals' => $signals,
            'next_hint' => self::NEXT_HINT[$level],
        ];
    }
}

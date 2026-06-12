<?php

namespace App\Services;

use App\Models\AbilityEvalAxis;
use App\Models\AbilityEvalItem;
use App\Models\AbilityEvalScoreCriterion;
use App\Models\AbilityScore;
use App\Models\AbilitySubjectiveScore;
use App\Models\Student;

/**
 * 能力評価 P4: 児童の「評価状況の全体像」を組み立てる。
 *
 * 各項目の最新スコア(ability_scores)を領域ごとにまとめ、レーダー(領域平均)+詳細表
 * (点数・段階/水準・保護者向けのことば・要確認)に使えるデータと、個別支援計画AIの
 * プロンプトへ差し込む要約テキストを提供する。
 */
class AbilitySummaryService
{
    /** ツールの表示順。 */
    private const TOOL_ORDER = ['DEV' => 0, 'ADV' => 1, 'WRK' => 2, 'UNV' => 3];

    /**
     * 児童の全体像を構造化して返す。
     *
     * @return array<string, mixed>
     */
    public function forStudent(Student $student): array
    {
        // 項目ごとの最新スコア
        $latest = AbilityScore::where('student_id', $student->id)
            ->orderByDesc('evaluated_on')->orderByDesc('id')
            ->get()
            ->unique('item_id');

        if ($latest->isEmpty()) {
            return [
                'has_data' => false, 'has_subjective' => false,
                'mynameis_member_code' => $student->mynameis_member_code,
                'domains' => [], 'radar' => [],
                'counts' => ['scored' => 0, 'needs_review' => 0, 'subjective' => 0],
            ];
        }

        $items = AbilityEvalItem::whereIn('item_id', $latest->pluck('item_id'))
            ->get(['item_id', 'tool_id', 'domain', 'name'])->keyBy('item_id');
        $axes = AbilityEvalAxis::pluck('name', 'axis_id');
        $guardianWords = AbilityEvalScoreCriterion::pluck('guardian_words', 'score');

        // mynameis 由来の主観自己評価(項目→1〜5)。客観(0〜10)と並べる。
        $subjective = AbilitySubjectiveScore::where('student_id', $student->id)
            ->pluck('response_value', 'item_id');

        // 項目を領域でまとめる
        $rows = $latest->map(function ($s) use ($items, $axes, $guardianWords, $subjective) {
            $item = $items->get($s->item_id);
            $subjVal = $subjective[$s->item_id] ?? null;

            return [
                'item_id' => $s->item_id,
                'tool_id' => $item?->tool_id,
                'domain' => $item?->domain ?? 'その他',
                'item_name' => $item?->name,
                'score' => $s->score,
                'axis_id' => $s->axis_id,
                'axis_name' => $axes[$s->axis_id] ?? null,
                'guardian_words' => $guardianWords[$s->score] ?? null,
                'needs_review' => (bool) $s->needs_review,
                // 主観(1〜5)と、客観0〜10へ正規化した値((v-1)/4*10)
                'subjective' => $subjVal,
                'subjective_norm' => $subjVal !== null ? round(($subjVal - 1) / 4 * 10, 1) : null,
                'evaluated_on' => $s->evaluated_on instanceof \Illuminate\Support\Carbon
                    ? $s->evaluated_on->toDateString() : (string) $s->evaluated_on,
            ];
        });

        $domains = $rows->groupBy('domain')->map(function ($group, $domain) {
            $subjNorms = $group->pluck('subjective_norm')->filter(fn ($v) => $v !== null);

            return [
                'domain' => $domain,
                'tool_id' => $group->first()['tool_id'],
                'average' => round($group->pluck('score')->avg(), 1),
                'subjective_average' => $subjNorms->isNotEmpty() ? round($subjNorms->avg(), 1) : null,
                'items' => $group->sortBy('item_id')->values()->all(),
            ];
        })->sortBy(fn ($d) => (self::TOOL_ORDER[$d['tool_id']] ?? 9) . '|' . $d['domain'])->values();

        return [
            'has_data' => true,
            'has_subjective' => $subjective->isNotEmpty(),
            'mynameis_member_code' => $student->mynameis_member_code,
            'domains' => $domains->all(),
            'radar' => $domains->map(fn ($d) => [
                'domain' => $d['domain'],
                'average' => $d['average'],
                'subjective' => $d['subjective_average'],
            ])->all(),
            'counts' => [
                'scored' => $rows->count(),
                'needs_review' => $rows->where('needs_review', true)->count(),
                'subjective' => $subjective->count(),
            ],
        ];
    }

    /**
     * 個別支援計画AIのプロンプトに差し込む要約テキストを作る。データが無ければ空文字。
     *
     * @param  array<string, mixed>  $summary
     */
    public function toPromptText(array $summary): string
    {
        if (empty($summary['has_data'])) {
            return '';
        }

        $lines = ["【能力評価(客観・直近のスコア0〜10)】"];
        foreach ($summary['domains'] as $d) {
            $lines[] = "■{$d['domain']}(平均{$d['average']})";
            foreach ($d['items'] as $it) {
                $stage = $it['axis_name'] ? "／{$it['axis_name']}" : '';
                $lines[] = "・{$it['item_name']}{$stage}: {$it['score']}点"
                    . ($it['guardian_words'] ? "({$it['guardian_words']})" : '');
            }
        }

        return implode("\n", $lines) . "\n\n";
    }
}

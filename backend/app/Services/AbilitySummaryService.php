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
        // 項目ごとの最新の客観スコア(まだ無いこともある)
        $latest = AbilityScore::where('student_id', $student->id)
            ->orderByDesc('evaluated_on')->orderByDesc('id')
            ->get()
            ->unique('item_id');

        // mynameis 由来の主観自己評価(項目→1〜5)。客観(0〜10)と並べる。
        $subjective = AbilitySubjectiveScore::where('student_id', $student->id)
            ->pluck('response_value', 'item_id');

        // 客観も主観も無ければデータなし(客観0でも主観があれば下で全体像を出す)
        if ($latest->isEmpty() && $subjective->isEmpty()) {
            return [
                'has_data' => false, 'has_subjective' => false,
                'mynameis_member_code' => $student->mynameis_member_code,
                'domains' => [], 'radar' => [],
                'counts' => ['scored' => 0, 'needs_review' => 0, 'subjective' => 0],
            ];
        }

        $objByItem = $latest->keyBy('item_id');
        // 表示対象 = 客観がある項目 ∪ 主観がある項目(客観0件でも主観だけで全体像を出す)
        $itemIds = collect($objByItem->keys())->merge($subjective->keys())->unique()->values();

        $items = AbilityEvalItem::whereIn('item_id', $itemIds)
            ->get(['item_id', 'tool_id', 'domain', 'name'])->keyBy('item_id');
        $axes = AbilityEvalAxis::pluck('name', 'axis_id');
        $guardianWords = AbilityEvalScoreCriterion::pluck('guardian_words', 'score');

        // 項目を領域でまとめる(客観・主観いずれか一方だけでも行を作る)
        $rows = $itemIds->map(function ($itemId) use ($items, $axes, $guardianWords, $subjective, $objByItem) {
            $item = $items->get($itemId);
            $s = $objByItem->get($itemId); // 客観(無いこともある)
            $subjVal = $subjective[$itemId] ?? null;

            return [
                'item_id' => $itemId,
                'tool_id' => $item?->tool_id,
                'domain' => $item?->domain ?? 'その他',
                'item_name' => $item?->name,
                'score' => $s?->score,
                'axis_id' => $s?->axis_id,
                'axis_name' => $s ? ($axes[$s->axis_id] ?? null) : null,
                'guardian_words' => $s ? ($guardianWords[$s->score] ?? null) : null,
                'needs_review' => (bool) ($s?->needs_review ?? false),
                // 主観(1〜5)と、客観0〜10へ正規化した値((v-1)/4*10)
                'subjective' => $subjVal,
                'subjective_norm' => $subjVal !== null ? round(($subjVal - 1) / 4 * 10, 1) : null,
                'evaluated_on' => $s && $s->evaluated_on instanceof \Illuminate\Support\Carbon
                    ? $s->evaluated_on->toDateString() : ($s ? (string) $s->evaluated_on : null),
            ];
        });

        $domains = $rows->groupBy('domain')->map(function ($group, $domain) {
            $scores = $group->pluck('score')->filter(fn ($v) => $v !== null);
            $subjNorms = $group->pluck('subjective_norm')->filter(fn ($v) => $v !== null);

            return [
                'domain' => $domain,
                'tool_id' => $group->first()['tool_id'],
                // 客観がまだ無い領域は average=null(主観のみ表示)
                'average' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
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
                'scored' => $rows->whereNotNull('score')->count(),
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
            // 客観がまだ無い領域(主観のみ)はプロンプトに出さない
            if ($d['average'] === null) {
                continue;
            }
            $lines[] = "■{$d['domain']}(平均{$d['average']})";
            foreach ($d['items'] as $it) {
                if (($it['score'] ?? null) === null) {
                    continue; // 客観スコアのある項目のみ
                }
                $stage = $it['axis_name'] ? "／{$it['axis_name']}" : '';
                $lines[] = "・{$it['item_name']}{$stage}: {$it['score']}点"
                    . ($it['guardian_words'] ? "({$it['guardian_words']})" : '');
            }
        }

        // 客観スコアのある領域が1つも無ければ(主観のみ)プロンプトには出さない
        if (count($lines) <= 1) {
            return '';
        }

        return implode("\n", $lines) . "\n\n";
    }
}

<?php

namespace App\Services;

use App\Models\AbilityEvalBenchmark;
use App\Models\AbilityEvalItem;
use App\Models\AbilityObservation;
use App\Models\Student;
use App\Support\AbilityGrowthStage;

/**
 * 能力評価: 日々の設問を「成長段階に合わせて・完全ランダムでなく」選定する。
 *
 * 方針(ユーザー合意): DEV(発達段階別・全児童)の25項目のうち、その児童の観察記録が
 * 最も少ない項目を優先して出題し、直近に出題した項目は避ける(記録の薄い項目を満遍なく
 * 埋めるローテーション)。設問文はその児童の成長段階軸(S1〜S6)の到達目安を用いる。
 * P3 でスコアが付いたら未達・最近接領域(スコア4-6)優先へ発展させる余地を残す。
 */
class AbilityQuestionService
{
    /** 対象ツール(発達段階別)。 */
    public const TOOL = 'DEV';

    /**
     * 次に出題する評価項目を選ぶ。候補が無ければ null。
     *
     * @param  string|null  $excludeItemId  差し替え(別の設問にする)で除外する項目
     */
    public function nextItemFor(Student $student, ?string $excludeItemId = null): ?AbilityEvalItem
    {
        $items = AbilityEvalItem::where('tool_id', self::TOOL)
            ->orderBy('item_id')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        // 児童の項目別の観察回数・最終観察を集計
        $stats = AbilityObservation::where('student_id', $student->id)
            ->selectRaw('item_id, count(*) as cnt, max(observed_date) as last_date')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        // 直近に出題した項目(最後に作成された観察の項目)は避ける
        $latestItemId = AbilityObservation::where('student_id', $student->id)
            ->orderByDesc('id')
            ->value('item_id');

        $candidates = $items->filter(fn ($it) => $it->item_id !== $excludeItemId)->values();
        if ($candidates->isEmpty()) {
            $candidates = $items;
        }

        // 直近項目を除いた候補があるならそれを使う(無ければ直近項目も許可)
        $withoutLatest = $candidates->filter(fn ($it) => $it->item_id !== $latestItemId)->values();
        if ($withoutLatest->isNotEmpty()) {
            $candidates = $withoutLatest;
        }

        // 観察回数昇順 → 最終観察が古い順(未観察=最優先) → 項目ID順 で安定ソート
        return $candidates->sortBy(function ($it) use ($stats) {
            $s = $stats->get($it->item_id);
            $cnt = $s ? (int) $s->cnt : 0;
            $last = $s && $s->last_date ? $s->last_date : '0000-00-00';

            return sprintf('%05d|%s|%s', $cnt, $last, $it->item_id);
        })->first();
    }

    /**
     * 設問の表示用ペイロードを組み立てる。
     *
     * @return array<string, mixed>
     */
    public function buildQuestion(Student $student, AbilityEvalItem $item): array
    {
        $axisId = AbilityGrowthStage::forStudent($student);

        $benchmark = AbilityEvalBenchmark::where('item_id', $item->item_id)
            ->where('axis_id', $axisId)
            ->value('benchmark');

        $axisName = \App\Models\AbilityEvalAxis::where('axis_id', $axisId)->value('name');

        return [
            'item_id' => $item->item_id,
            'domain' => $item->domain,
            'item_name' => $item->name,
            'definition' => $item->definition,
            'perspective' => $item->perspective,
            'axis_id' => $axisId,
            'axis_name' => $axisName,
            'benchmark' => $benchmark,
        ];
    }
}

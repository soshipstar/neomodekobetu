<?php

namespace App\Services;

use App\Models\AbilityEvalBenchmark;
use App\Models\AbilityEvalItem;
use App\Models\AbilityObservation;
use App\Models\AbilityScore;
use App\Models\Student;
use App\Support\AbilityToolScope;

/**
 * 能力評価: 日々の設問を「発達段階に合わせて・完全ランダムでなく」選定する。
 *
 * 方針(現場フィードバック 2026-06-24 で見直し):
 *  - DEV(発達段階別・5領域)を主軸とし、ADV(高卒標準・学力)等より優先して出題する
 *    ([[AbilityToolScope]] でツール対象を決定。中学生以下は DEV のみ)。
 *  - その児童の観察記録が最も少ない項目を優先し、直近に出題した項目は避ける(満遍なく
 *    埋めるローテーション。同点ならツール優先度 DEV→ADV→WRK→UNV)。
 *  - 設問文(到達目安)は小学校低学年(S1)から始める([[AbilityToolScope]]::axisFor)。
 *    達成(スコア≥8)で S2→S3… と段階を上げる自動進行は次フェーズで対応。
 */
class AbilityQuestionService
{
    /**
     * 次に出題する評価項目を選ぶ。候補が無ければ null。
     *
     * 出題対象は児童ごとの適用ツール(DEV/ADV、中学生以上はWRK/UNVも)の全項目。
     *
     * @param  string|null  $excludeItemId  差し替え(別の設問にする)で除外する項目
     */
    public function nextItemFor(Student $student, ?string $excludeItemId = null): ?AbilityEvalItem
    {
        $items = AbilityEvalItem::whereIn('tool_id', AbilityToolScope::toolsFor($student))
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

        // DEV(発達5領域)を主軸にするためツール優先度を入れる。同じ観察回数なら
        // DEV → ADV → WRK → UNV の順。これで「科目指導的(ADV)の設問ばかりが先に出る」
        // のを防ぎ、まず発達の中核項目を満遍なく埋める(現場フィードバック 2026-06-24)。
        $toolPriority = ['DEV' => 0, 'ADV' => 1, 'WRK' => 2, 'UNV' => 3];

        // 観察回数昇順 → ツール優先度(DEV優先) → 最終観察が古い順(未観察=最優先) → 項目ID順
        return $candidates->sortBy(function ($it) use ($stats, $toolPriority) {
            $s = $stats->get($it->item_id);
            $cnt = $s ? (int) $s->cnt : 0;
            $last = $s && $s->last_date ? $s->last_date : '0000-00-00';
            $tp = $toolPriority[$it->tool_id] ?? 9;

            return sprintf('%05d|%d|%s|%s', $cnt, $tp, $last, $it->item_id);
        })->first();
    }

    /**
     * その児童・その項目で「今取り組む学年帯(評価軸)」を返す。
     *
     * 低い軸から順に見て、まだ到達(最新スコア ≥ しきい値)していない最も低い軸を出題する。
     * 未スコアは開始軸(DEV=S1 / ADV=L1)から。全軸到達済みなら最上位を維持。
     * これにより「小学校低学年(S1)から始め、到達したら一段上げる」段階進行になる。
     */
    public function currentAxisFor(Student $student, AbilityEvalItem $item): string
    {
        $axes = AbilityToolScope::axesForTool($item->tool_id);

        foreach ($axes as $axis) {
            $latest = AbilityScore::where('student_id', $student->id)
                ->where('item_id', $item->item_id)
                ->where('axis_id', $axis)
                ->orderByDesc('evaluated_on')->orderByDesc('id')
                ->value('score');

            if ($latest === null || (int) $latest < AbilityToolScope::ACHIEVED_THRESHOLD) {
                return $axis;
            }
        }

        return (string) end($axes);
    }

    /**
     * 設問の表示用ペイロードを組み立てる。
     *
     * @return array<string, mixed>
     */
    public function buildQuestion(Student $student, AbilityEvalItem $item): array
    {
        $axisId = $this->currentAxisFor($student, $item);

        // ADV は到達水準、WRK/UNV は時期の到達目安。WRK/UNV は到達目安が無い項目もあり null 可。
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

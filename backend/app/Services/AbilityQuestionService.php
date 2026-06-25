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
        $exclude = $excludeItemId !== null && $excludeItemId !== '' ? [$excludeItemId] : [];

        return $this->nextItemsFor($student, 1, $exclude)->first();
    }

    /**
     * 次に出題する評価項目を最大 $count 件、ローテーション順で選ぶ(1日複数問用)。
     *
     * 出題対象は児童ごとの適用ツール(DEV主軸、高校段階はADV/WRK/UNVも)の全項目。
     * 観察回数の少ない順 → DEV優先 → 最終観察が古い順 → 項目ID順。直近に出した項目は後ろへ。
     *
     * @param  array<int, string>  $excludeItemIds 差し替え等で除外する項目
     * @return \Illuminate\Support\Collection<int, AbilityEvalItem>
     */
    public function nextItemsFor(Student $student, int $count = 3, array $excludeItemIds = []): \Illuminate\Support\Collection
    {
        $items = AbilityEvalItem::whereIn('tool_id', AbilityToolScope::toolsFor($student))
            ->orderBy('item_id')
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        // 児童の項目別の観察回数・最終観察を集計
        $stats = AbilityObservation::where('student_id', $student->id)
            ->selectRaw('item_id, count(*) as cnt, max(observed_date) as last_date')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        // 直近に出題した項目は後ろへ回す(同時出題で連続しないように)
        $latestItemId = AbilityObservation::where('student_id', $student->id)
            ->orderByDesc('id')
            ->value('item_id');

        $exclude = array_flip($excludeItemIds);
        // DEV(発達5領域)主軸: 同じ観察回数なら DEV → ADV → WRK → UNV の順。
        $toolPriority = ['DEV' => 0, 'ADV' => 1, 'WRK' => 2, 'UNV' => 3];

        return $items
            ->filter(fn ($it) => ! isset($exclude[$it->item_id]))
            ->sortBy(function ($it) use ($stats, $toolPriority, $latestItemId) {
                $s = $stats->get($it->item_id);
                $cnt = $s ? (int) $s->cnt : 0;
                $last = $s && $s->last_date ? $s->last_date : '0000-00-00';
                $tp = $toolPriority[$it->tool_id] ?? 9;
                $recent = $it->item_id === $latestItemId ? 1 : 0;

                // 直近項目→観察回数昇順→ツール優先→最終観察が古い順→項目ID順
                return sprintf('%d|%05d|%d|%s|%s', $recent, $cnt, $tp, $last, $it->item_id);
            })
            ->take(max(1, $count))
            ->values();
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
    public function buildQuestion(Student $student, AbilityEvalItem $item, ?string $forceAxis = null): array
    {
        // 回答済みの設問は回答時の学年帯で表示するため軸を指定できる。既定は進行中の段階。
        $axisId = $forceAxis ?? $this->currentAxisFor($student, $item);

        // ADV は到達水準、WRK/UNV は時期の到達目安。WRK/UNV は到達目安が無い項目もあり null 可。
        $benchmark = AbilityEvalBenchmark::where('item_id', $item->item_id)
            ->where('axis_id', $axisId)
            ->value('benchmark');

        $axisName = \App\Models\AbilityEvalAxis::where('axis_id', $axisId)->value('name');

        // 段階別の具体設問(AI生成・編集可)があれば、それを問いとして優先する。
        // 無ければ到達目安テキストをそのまま問いに使う(フォールバック)。
        $stageQuestion = \App\Models\AbilityStageQuestion::where('item_id', $item->item_id)
            ->where('axis_id', $axisId)
            ->where('is_active', true)
            ->first();

        return [
            'item_id' => $item->item_id,
            'domain' => $item->domain,
            'item_name' => $item->name,
            'definition' => $item->definition,
            'perspective' => $item->perspective,
            'axis_id' => $axisId,
            'axis_name' => $axisName,
            'benchmark' => $benchmark,
            // 具体設問(あれば生成設問、無ければ到達目安)＋観察ヒント
            'question' => $stageQuestion?->question ?: $benchmark,
            'hint' => $stageQuestion?->hint,
        ];
    }
}

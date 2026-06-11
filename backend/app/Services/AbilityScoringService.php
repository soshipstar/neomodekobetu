<?php

namespace App\Services;

use App\Models\AbilityEvalItem;
use App\Models\AbilityObservation;
use App\Models\AbilityScore;
use App\Models\Student;
use App\Support\AbilityToolScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 能力評価 P3: 観察記録から評価スコアを決定的に算出するルールエンジン(AI不使用)。
 *
 * 評価表「評価基準(詳細)」の判定フローに忠実に実装する:
 *  1. 集計は直近3か月。同一項目の(支援コード付き)記録が3件未満なら変更しない(記録不足)。
 *  2. 『支援の量』が最優先。支援なし(SUP0)が過半数なら成功率で6〜8点。
 *     そうでなければ最頻の支援レベルの基準点(1〜5点)。
 *  3. 9点は「支援なしで異なる2場面以上(うち1つは新規場面)」がある場合のみ。
 *     10点(他者援助・指導・自発的工夫)は本エンジンでは自動付与せず人間判断に委ねる。
 *  4. 1回の更新の変動は±2点に制限。±3点以上が示唆されたら「要人間確認」フラグ。
 *  6. 変更時は根拠記録(日付・行動)を備考に自動転記する。
 *
 * (ルール5: 体調不良・大きな環境変化の期間を下降判定から除外、は専用フラグが未実装のため
 *  本フェーズでは対象外。観察記録に区分を持たせた段階で対応する。)
 */
class AbilityScoringService
{
    /** 支援コード → 基準点(1〜5)。SUP0 は成功率で6〜8を別途判定。 */
    private const SUPPORT_BASE = [
        'SUP1' => 5, // 声かけ1回
        'SUP2' => 4, // 声かけ2〜3回
        'SUP3' => 4, // 視覚支援
        'SUP4' => 3, // 手本・繰り返しの促し
        'SUP5' => 2, // 部分的な手添え(結果が途中中心なら1へ)
        'SUP6' => 2, // 常時の手添え
    ];

    /**
     * 児童の全 DEV 項目を採点し、スコアが変わった項目は ability_scores に追記する。
     *
     * @return array<int, array<string, mixed>> 項目ごとの結果(status: scored/unchanged/insufficient)
     */
    public function scoreStudent(Student $student, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::now();
        $since = $asOf->copy()->subMonths(3)->startOfDay();

        // 出題対象と同じ適用ツール(DEV/ADV、中学生以上はWRK/UNVも)を採点する
        $items = AbilityEvalItem::whereIn('tool_id', AbilityToolScope::toolsFor($student, $asOf))
            ->orderBy('item_id')->get(['item_id', 'tool_id']);

        // 直近3か月の観察を項目ごとに集める
        $byItem = AbilityObservation::where('student_id', $student->id)
            ->whereBetween('observed_date', [$since->toDateString(), $asOf->toDateString()])
            ->get()
            ->groupBy('item_id');

        $results = [];
        foreach ($items as $item) {
            $obs = $byItem->get($item->item_id, collect());
            $axisId = AbilityToolScope::axisFor($student, $item->tool_id, $asOf);
            $results[] = $this->evaluateItem($student, $item->item_id, $axisId, $obs, $asOf);
        }

        return $results;
    }

    /**
     * 1項目を採点する。必要なら ability_scores に新しい行を追記する。
     *
     * @param  Collection<int, AbilityObservation>  $obs
     * @return array<string, mixed>
     */
    private function evaluateItem(Student $student, string $itemId, string $axisId, Collection $obs, Carbon $asOf): array
    {
        // 採点に使うのは支援コードが記録された観察のみ
        $scoring = $obs->filter(fn ($o) => $o->support_code !== null)->values();

        if ($scoring->count() < 3) {
            return ['item_id' => $itemId, 'status' => 'insufficient', 'count' => $scoring->count()];
        }

        $raw = $this->rawScore($scoring);

        $prev = AbilityScore::where('student_id', $student->id)
            ->where('item_id', $itemId)
            ->orderByDesc('evaluated_on')->orderByDesc('id')
            ->first();
        $prevScore = $prev?->score;

        // ルール4: 変動は±2に制限、±3以上の示唆は要人間確認
        $needsReview = false;
        $applied = $raw;
        if ($prevScore !== null) {
            $delta = $raw - $prevScore;
            if (abs($delta) >= 3) {
                $needsReview = true;
                $applied = $prevScore + ($delta > 0 ? 2 : -2);
            }
        }

        // 変化が無ければ追記しない(履歴を汚さない)
        if ($prevScore !== null && $applied === $prevScore) {
            return ['item_id' => $itemId, 'status' => 'unchanged', 'score' => $applied, 'count' => $scoring->count()];
        }

        $evidence = $scoring->sortByDesc('observed_date')->take(5);
        $score = AbilityScore::create([
            'student_id' => $student->id,
            'item_id' => $itemId,
            'axis_id' => $axisId,
            'score' => $applied,
            'prev_score' => $prevScore,
            'change' => $prevScore === null ? null : $applied - $prevScore,
            'needs_review' => $needsReview,
            'method' => 'rule_engine',
            'evidence_record_ids' => $evidence->pluck('id')->values()->all(),
            'notes' => $this->transcribeEvidence($evidence),
            'evaluated_on' => $asOf->toDateString(),
        ]);

        return [
            'item_id' => $itemId,
            'status' => 'scored',
            'score' => $applied,
            'prev_score' => $prevScore,
            'change' => $score->change,
            'needs_review' => $needsReview,
            'count' => $scoring->count(),
        ];
    }

    /**
     * 判定フローに沿って生スコア(0〜10)を算出する。
     *
     * @param  Collection<int, AbilityObservation>  $scoring
     */
    private function rawScore(Collection $scoring): int
    {
        $total = $scoring->count();
        $sup0 = $scoring->filter(fn ($o) => $o->support_code === 'SUP0')->values();

        // ルール2: 支援なし(SUP0)が過半数なら成功率で6〜8点
        if ($sup0->count() * 2 > $total) {
            $completed = $sup0->filter(fn ($o) => $o->result === 'completed')->count();
            $rate = $sup0->count() > 0 ? $completed / $sup0->count() : 0.0;

            $base = match (true) {
                $rate >= 0.9 => 8,
                $rate >= 0.8 => 7,
                $rate >= 0.5 => 6,
                default => 5, // 支援なしだが成功率が低い(芽生え)
            };

            // ルール3: 般化(9点) = 支援なし完了が異なる2場面以上、うち1つは新規場面
            if ($base >= 8 && $this->isGeneralized($sup0)) {
                return 9;
            }

            return $base;
        }

        // 支援が必要な記録が過半: 最頻の支援レベルの基準点(1〜5)
        $needed = $scoring->filter(fn ($o) => $o->support_code !== 'SUP0')->values();
        $mostFrequent = $needed->groupBy('support_code')
            ->sortByDesc(fn ($g) => $g->count())
            ->keys()
            ->first();

        $base = self::SUPPORT_BASE[$mostFrequent] ?? 3;

        // 手添え(SUP5/SUP6)は、結果が「完了」中心なら2、そうでなければ1
        if (in_array($mostFrequent, ['SUP5', 'SUP6'], true)) {
            $group = $needed->filter(fn ($o) => $o->support_code === $mostFrequent);
            $completed = $group->filter(fn ($o) => $o->result === 'completed')->count();
            $base = $completed * 2 >= $group->count() ? 2 : 1;
        }

        return $base;
    }

    /**
     * 般化判定: 支援なし(SUP0)完了が、異なる2場面以上で確認され、うち1つは新規場面か。
     *
     * @param  Collection<int, AbilityObservation>  $sup0
     */
    private function isGeneralized(Collection $sup0): bool
    {
        $completed = $sup0->filter(fn ($o) => $o->result === 'completed');
        if ($completed->isEmpty()) {
            return false;
        }

        // 場面の区別は daily_record_id(無ければ観察ID)で行う
        $scenes = $completed->map(fn ($o) => $o->daily_record_id ?? 'obs-' . $o->id)->unique();
        $hasNew = $completed->contains(fn ($o) => (bool) $o->is_new_scene);

        return $scenes->count() >= 2 && $hasNew;
    }

    /**
     * 根拠記録を「日付: 行動」の形で備考へ転記する。
     *
     * @param  Collection<int, AbilityObservation>  $evidence
     */
    private function transcribeEvidence(Collection $evidence): string
    {
        return $evidence->map(function ($o) {
            $date = $o->observed_date instanceof Carbon ? $o->observed_date->toDateString() : (string) $o->observed_date;
            $behavior = $o->behavior ?: '(行動記録なし)';

            return "{$date}: {$behavior}";
        })->implode("\n");
    }
}

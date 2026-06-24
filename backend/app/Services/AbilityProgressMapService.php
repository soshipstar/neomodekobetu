<?php

namespace App\Services;

use App\Models\AbilityEvalAxis;
use App\Models\AbilityEvalItem;
use App\Models\AbilityScore;
use App\Models\Student;
use App\Support\AbilityToolScope;
use Illuminate\Support\Carbon;

/**
 * 能力評価「到達マップ」: 児童ごとに「項目×学年帯(評価軸)」の到達状況と、
 * 期間内(既定6か月)の成長(新規到達マス数・スコア増分・伸びた項目)を構造化して返す。
 *
 * セル状態は当該マスの最新スコアで決定:
 *   未着手(スコア無) / 途上(1-7) / 到達(しきい値=8) / 般化(9-10)。
 * 成長は ability_scores の追記履歴から「since時点」と「as_of時点」の最新スコアを比較する
 * (スナップショット表は持たない)。
 */
class AbilityProgressMapService
{
    /**
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, ?Carbon $since = null, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::now();
        $since = $since ?? $asOf->copy()->subMonths(6);
        $threshold = AbilityToolScope::ACHIEVED_THRESHOLD;

        $tools = AbilityToolScope::toolsFor($student, $asOf);

        $items = AbilityEvalItem::whereIn('tool_id', $tools)
            ->orderBy('item_id')
            ->get(['item_id', 'tool_id', 'domain', 'name'])
            ->groupBy('tool_id');

        $axisNames = AbilityEvalAxis::pluck('name', 'axis_id');

        // 児童の全スコア履歴を (item|axis) でまとめ、評価日昇順に並べる
        $history = AbilityScore::where('student_id', $student->id)
            ->orderBy('evaluated_on')->orderBy('id')
            ->get(['item_id', 'axis_id', 'score', 'evaluated_on'])
            ->groupBy(fn ($s) => $s->item_id . '|' . $s->axis_id);

        $dateOf = fn ($r) => $r->evaluated_on instanceof Carbon
            ? $r->evaluated_on : Carbon::parse((string) $r->evaluated_on);

        // 指定日以前の「最新スコア」(履歴は昇順なので最後に残るものが最新)
        $latestAsOf = function (string $key, Carbon $date) use ($history, $dateOf): ?int {
            $rows = $history->get($key);
            if (! $rows) {
                return null;
            }
            $latest = null;
            foreach ($rows as $r) {
                if ($dateOf($r)->lte($date)) {
                    $latest = (int) $r->score;
                }
            }

            return $latest;
        };

        // 到達日 = しきい値以上に最初に到達した評価日(as_of以前)
        $achievedOn = function (string $key, Carbon $date) use ($history, $dateOf, $threshold): ?string {
            foreach ($history->get($key, collect()) as $r) {
                if ($dateOf($r)->lte($date) && (int) $r->score >= $threshold) {
                    return $dateOf($r)->toDateString();
                }
            }

            return null;
        };

        $statusFor = function (?int $score) use ($threshold): string {
            if ($score === null) {
                return 'not_started';
            }
            if ($score >= 9) {
                return 'generalized';
            }

            return $score >= $threshold ? 'achieved' : 'in_progress';
        };

        $achievedDelta = 0;
        $scoreGain = 0;
        $growthByDomain = [];
        $topItems = [];
        $toolsOut = [];

        foreach ($tools as $toolId) {
            $toolItems = $items->get($toolId, collect());
            if ($toolItems->isEmpty()) {
                continue;
            }
            $axes = AbilityToolScope::axesForTool($toolId);
            $axesOut = array_map(fn ($a) => ['axis_id' => $a, 'name' => $axisNames[$a] ?? $a], $axes);

            $domainsOut = [];
            foreach ($toolItems->groupBy('domain') as $domain => $group) {
                $itemsOut = [];
                foreach ($group as $it) {
                    $cells = [];
                    $reached = null;        // as_of時点で到達済みの最高軸
                    $reachedSince = null;   // since時点で到達済みの最高軸
                    $current = null;        // 今取り組む(未到達の最も低い)軸

                    foreach ($axes as $axis) {
                        $key = $it->item_id . '|' . $axis;
                        $now = $latestAsOf($key, $asOf);
                        $before = $latestAsOf($key, $since);
                        $status = $statusFor($now);

                        $cells[] = [
                            'axis_id' => $axis,
                            'score' => $now,
                            'status' => $status,
                            'achieved_on' => in_array($status, ['achieved', 'generalized'], true)
                                ? $achievedOn($key, $asOf) : null,
                        ];

                        if (($now ?? 0) >= $threshold) {
                            $reached = $axis;
                        } elseif ($current === null) {
                            $current = $axis;
                        }
                        if (($before ?? 0) >= $threshold) {
                            $reachedSince = $axis;
                        }

                        // 成長集計
                        if (($now ?? 0) >= $threshold && ($before ?? 0) < $threshold) {
                            $achievedDelta++;
                            $growthByDomain[$domain]['achieved_delta'] = ($growthByDomain[$domain]['achieved_delta'] ?? 0) + 1;
                        }
                        $gain = max(0, ($now ?? 0) - ($before ?? 0));
                        if ($gain > 0) {
                            $scoreGain += $gain;
                            $growthByDomain[$domain]['score_gain'] = ($growthByDomain[$domain]['score_gain'] ?? 0) + $gain;
                        }
                    }

                    if ($reached !== null && $reached !== $reachedSince) {
                        $topItems[] = [
                            'item_id' => $it->item_id,
                            'item_name' => $it->name,
                            'from' => $reachedSince ? ($axisNames[$reachedSince] ?? $reachedSince) : '未到達',
                            'to' => $axisNames[$reached] ?? $reached,
                        ];
                    }

                    $itemsOut[] = [
                        'item_id' => $it->item_id,
                        'item_name' => $it->name,
                        'current_axis' => $current ?? (string) end($axes),
                        'reached_axis' => $reached,
                        'cells' => $cells,
                    ];
                }
                $domainsOut[] = ['domain' => $domain, 'items' => $itemsOut];
            }

            $toolsOut[] = ['tool_id' => $toolId, 'axes' => $axesOut, 'domains' => $domainsOut];
        }

        $byDomainOut = [];
        foreach ($growthByDomain as $domain => $g) {
            $byDomainOut[] = [
                'domain' => $domain,
                'achieved_delta' => $g['achieved_delta'] ?? 0,
                'score_gain' => $g['score_gain'] ?? 0,
            ];
        }

        return [
            'student' => ['id' => $student->id, 'name' => $student->student_name],
            'since' => $since->toDateString(),
            'as_of' => $asOf->toDateString(),
            'threshold' => $threshold,
            'tools' => $toolsOut,
            'growth' => [
                'achieved_delta' => $achievedDelta,
                'score_gain_total' => $scoreGain,
                'by_domain' => $byDomainOut,
                'top_items' => $topItems,
            ],
        ];
    }
}

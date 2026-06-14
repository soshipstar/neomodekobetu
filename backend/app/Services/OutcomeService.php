<?php

namespace App\Services;

use App\Models\AbilityScore;
use App\Models\MonitoringRecord;
use App\Models\Student;

/**
 * AI学習基盤 S6: 成果(outcome)の算出(企画書 §13)。
 *
 *  A 能力評価スコアの変化(Δ): ability_scores.change(採点エンジンが算出済)を最新行で集計。
 *  B モニタリング達成度: 最新モニタリングの achievement_level(1〜5)を平均・正規化。
 *  C 主観×客観の一致: 客観(0-10) と 主観正規化(0-10) の領域別一致度(AbilitySummaryService 再利用)。
 *
 * いずれも当該児童の現況を返す閲覧用(担当職員の運用情報)。横断集計・同意ゲートは別途。
 */
class OutcomeService
{
    /** モニタリング達成度ラベル → 1〜5(数値入力が基本。AI生成ラベルも吸収)。 */
    private const LEVEL_MAP = [
        '大きく達成' => 5, '達成' => 4, '概ね達成' => 3, 'やや未達成' => 2, '未達成' => 1,
        '進行中' => 3, '継続中' => 3, '見直し必要' => 2, '未着手' => 1,
    ];

    public function __construct(private AbilitySummaryService $summary) {}

    public function forStudent(Student $student): array
    {
        return [
            'objective_delta' => $this->objectiveDelta($student),
            'monitoring' => $this->monitoringAchievement($student),
            'agreement' => $this->agreement($student),
        ];
    }

    /** A: 各項目の最新スコアの change を集計(向上/低下の数と平均Δ)。 */
    private function objectiveDelta(Student $student): array
    {
        $latest = AbilityScore::where('student_id', $student->id)
            ->orderByDesc('evaluated_on')->orderByDesc('id')->get()->unique('item_id');
        if ($latest->isEmpty()) {
            return ['has' => false];
        }
        $changes = $latest->pluck('change')->filter(fn ($v) => $v !== null);

        return [
            'has' => true,
            'scored_items' => $latest->count(),
            'avg_change' => $changes->isNotEmpty() ? round($changes->avg(), 2) : null,
            'improved' => $changes->filter(fn ($v) => $v > 0)->count(),
            'declined' => $changes->filter(fn ($v) => $v < 0)->count(),
            'latest_on' => optional($latest->max('evaluated_on'))->toString(),
        ];
    }

    /** B: 最新モニタリングの達成度(1〜5)平均と達成率(%)。 */
    private function monitoringAchievement(Student $student): array
    {
        $m = MonitoringRecord::where('student_id', $student->id)
            ->orderByDesc('monitoring_date')->with('details')->first();
        if (! $m) {
            return ['has' => false];
        }
        $levels = $m->details->map(fn ($d) => $this->levelToInt($d->achievement_level))->filter(fn ($v) => $v !== null)->values();
        if ($levels->isEmpty()) {
            return ['has' => false];
        }
        $avg = $levels->avg();

        return [
            'has' => true,
            'avg_level' => round($avg, 2),
            'pct' => (int) round(($avg - 1) / 4 * 100),
            'count' => $levels->count(),
            'monitoring_date' => optional($m->monitoring_date)->toDateString(),
        ];
    }

    /** C: 主観×客観の領域別一致度(0-100%)。両方が揃う領域のみ。 */
    private function agreement(Student $student): array
    {
        $sum = $this->summary->forStudent($student);
        if (empty($sum['has_subjective'])) {
            return ['has' => false];
        }
        $domains = collect($sum['domains'] ?? [])
            ->filter(fn ($d) => ($d['subjective_average'] ?? null) !== null && ($d['average'] ?? null) !== null);
        if ($domains->isEmpty()) {
            return ['has' => false];
        }
        $perDomain = $domains->map(fn ($d) => [
            'domain' => $d['domain'],
            'objective' => $d['average'],
            'subjective' => $d['subjective_average'],
            'agreement' => (int) round(max(0, 1 - abs($d['average'] - $d['subjective_average']) / 10) * 100),
        ])->values();

        return [
            'has' => true,
            'overall' => (int) round($perDomain->avg('agreement')),
            'domains' => $perDomain,
        ];
    }

    private function levelToInt(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $n = (int) $raw;

            return ($n >= 1 && $n <= 5) ? $n : null;
        }

        return self::LEVEL_MAP[$raw] ?? null;
    }
}

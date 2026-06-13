<?php

namespace App\Services;

use App\Models\AiEditMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AI学習基盤 Layer2: 期間×次元で「AI出力→人間修正」の傾向を集計する(ai_edit_metrics)。
 *
 * 鉄則:
 *  - 同意済みのみ: 現在の同意(児童 ai_consent_learning AND 施設 ai_consent_aggregate)でフィルタ(撤回反映)。
 *  - k-匿名: distinct_students < K(=5) のセルは保存しない。
 *  - 冪等: 期間単位で delete → insert。
 *
 * 次元(facet): company/classroom/cohort/growth_stage/author/document_type/support_category/program_category。
 * NULL の次元は「全体(ALL)」を意味する OLAP ロールアップ。
 */
class AiEditMetricsService
{
    public const K = 5;

    /** 全次元カラム(行テンプレート用)。 */
    private const DIM_COLUMNS = [
        'classroom_id', 'subj_cohort', 'subj_growth_stage', 'author_user_id',
        'document_type', 'support_category', 'program_category_id',
    ];

    /** 指定月(YYYY-MM)を再計算する。戻り値=保存したセル数。 */
    public function rebuild(string $periodYm): int
    {
        [$start, $end] = $this->bounds($periodYm);

        $revs = DB::table('ai_revision_events as r')
            ->join('students as s', 's.id', '=', 'r.student_id')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->join('companies as co', 'co.id', '=', 'c.company_id')
            ->where('r.changed', true)
            ->whereNotNull('r.company_id')
            ->where('s.ai_consent_learning', true)
            ->where('co.ai_consent_aggregate', true)
            ->where('r.created_at', '>=', $start)->where('r.created_at', '<', $end)
            ->select('r.id', 'r.company_id', 'r.classroom_id', 'r.student_id', 'r.document_type', 'r.document_id',
                'r.change_ratio', 'r.editor_user_id', 'r.subj_cohort', 'r.subj_growth_stage',
                'r.support_category', 'r.program_category_id')
            ->get();

        $reasonsByRev = $revs->isEmpty() ? collect() : DB::table('ai_edit_reasons')
            ->whereIn('ai_revision_event_id', $revs->pluck('id'))->whereNotNull('category_id')
            ->select('ai_revision_event_id', 'category_id')->get()->groupBy('ai_revision_event_id');

        $gens = DB::table('ai_generation_events as g')
            ->join('students as s', 's.id', '=', 'g.student_id')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->join('companies as co', 'co.id', '=', 'c.company_id')
            ->whereNotNull('g.company_id')
            ->where('s.ai_consent_learning', true)
            ->where('co.ai_consent_aggregate', true)
            ->where('g.generated_at', '>=', $start)->where('g.generated_at', '<', $end)
            ->select('g.company_id', 'g.classroom_id', 'g.document_type', 'g.subj_cohort', 'g.subj_growth_stage')
            ->get();

        // 生成数の事前集計(facet別の分母)。
        $gen = [
            'company' => $this->countBy($gens, fn ($g) => $g->company_id),
            'classroom' => $this->countBy($gens, fn ($g) => $g->classroom_id ? "{$g->company_id}|{$g->classroom_id}" : null),
            'cohort' => $this->countBy($gens, fn ($g) => $g->subj_cohort ? "{$g->company_id}|{$g->subj_cohort}" : null),
            'growth_stage' => $this->countBy($gens, fn ($g) => $g->subj_growth_stage ? "{$g->company_id}|{$g->subj_growth_stage}" : null),
            'document_type' => $this->countBy($gens, fn ($g) => $g->document_type ? "{$g->company_id}|{$g->document_type}" : null),
        ];

        // facet: [metricColumn => revisionProperty]。'req'=非NULL必須のrevプロパティ。'genGroup'=genキー。
        $facets = [
            ['facet' => 'company', 'cols' => ['company_id' => 'company_id'], 'genGroup' => 'company', 'genKey' => fn ($r) => $r->company_id],
            ['facet' => 'classroom', 'cols' => ['company_id' => 'company_id', 'classroom_id' => 'classroom_id'], 'req' => 'classroom_id', 'genGroup' => 'classroom', 'genKey' => fn ($r) => "{$r->company_id}|{$r->classroom_id}"],
            ['facet' => 'cohort', 'cols' => ['company_id' => 'company_id', 'subj_cohort' => 'subj_cohort'], 'genGroup' => 'cohort', 'genKey' => fn ($r) => "{$r->company_id}|{$r->subj_cohort}"],
            ['facet' => 'growth_stage', 'cols' => ['company_id' => 'company_id', 'subj_growth_stage' => 'subj_growth_stage'], 'genGroup' => 'growth_stage', 'genKey' => fn ($r) => "{$r->company_id}|{$r->subj_growth_stage}"],
            ['facet' => 'author', 'cols' => ['company_id' => 'company_id', 'author_user_id' => 'editor_user_id'], 'req' => 'editor_user_id'],
            ['facet' => 'document_type', 'cols' => ['company_id' => 'company_id', 'document_type' => 'document_type'], 'req' => 'document_type', 'genGroup' => 'document_type', 'genKey' => fn ($r) => "{$r->company_id}|{$r->document_type}"],
            ['facet' => 'support_category', 'cols' => ['company_id' => 'company_id', 'support_category' => 'support_category'], 'req' => 'support_category'],
            ['facet' => 'program_category', 'cols' => ['company_id' => 'company_id', 'program_category_id' => 'program_category_id'], 'req' => 'program_category_id'],
        ];

        $rows = [];
        foreach ($facets as $f) {
            $groups = [];
            foreach ($revs as $r) {
                if (isset($f['req']) && ($r->{$f['req']} === null || $r->{$f['req']} === '')) {
                    continue;
                }
                $gkey = implode('|', array_map(fn ($prop) => (string) $r->{$prop}, $f['cols']));
                $groups[$gkey][] = $r;
            }

            foreach ($groups as $members) {
                $students = array_unique(array_map(fn ($r) => $r->student_id, $members));
                if (count($students) < self::K) {
                    continue; // k-匿名
                }
                $rows[] = $this->buildRow($periodYm, $f, $members, $students, $reasonsByRev, $gen);
            }
        }

        DB::transaction(function () use ($periodYm, $rows) {
            AiEditMetric::where('period_ym', $periodYm)->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('ai_edit_metrics')->insert($chunk);
            }
        });

        return count($rows);
    }

    /** @param array<int,object> $members */
    private function buildRow(string $periodYm, array $facet, array $members, array $students, $reasonsByRev, array $gen): array
    {
        $first = $members[0];
        $docs = array_unique(array_map(fn ($r) => $r->document_type.':'.$r->document_id, $members));
        $ratios = array_values(array_filter(array_map(fn ($r) => $r->change_ratio, $members), fn ($v) => $v !== null));
        $avg = $ratios ? array_sum($ratios) / count($ratios) : null;

        $reasonCounts = [];
        foreach ($members as $r) {
            foreach (($reasonsByRev[$r->id] ?? []) as $reason) {
                $reasonCounts[$reason->category_id] = ($reasonCounts[$reason->category_id] ?? 0) + 1;
            }
        }
        arsort($reasonCounts);
        $top = [];
        foreach (array_slice($reasonCounts, 0, 3, true) as $cid => $cnt) {
            $top[] = ['category_id' => $cid, 'count' => $cnt];
        }

        $genCount = null;
        $editRate = null;
        if (isset($facet['genGroup'])) {
            $genCount = $gen[$facet['genGroup']][($facet['genKey'])($first)] ?? 0;
            $editRate = $genCount > 0 ? round(count($docs) / $genCount, 4) : null;
        }

        // 行テンプレート(全次元NULL初期化)
        $row = array_fill_keys(self::DIM_COLUMNS, null);
        $row['period_ym'] = $periodYm;
        $row['facet'] = $facet['facet'];
        $row['company_id'] = $first->company_id;
        foreach ($facet['cols'] as $metricCol => $prop) {
            $row[$metricCol] = $first->{$prop};
        }
        $row['revision_count'] = count($members);
        $row['edited_document_count'] = count($docs);
        $row['distinct_students'] = count($students);
        $row['gen_count'] = $genCount ?? 0;
        $row['edit_rate'] = $editRate;
        $row['change_ratio_avg'] = $avg !== null ? round($avg, 4) : null;
        $row['change_ratio_p50'] = $ratios ? round($this->percentile($ratios, 0.5), 4) : null;
        $row['change_ratio_p90'] = $ratios ? round($this->percentile($ratios, 0.9), 4) : null;
        $row['ai_acceptance'] = $avg !== null ? round(1 - $avg, 4) : null;
        $row['top_reason_categories'] = $top ? json_encode($top, JSON_UNESCAPED_UNICODE) : null;
        $row['computed_at'] = Carbon::now();

        return $row;
    }

    /** @return array<string,int> */
    private function countBy($items, callable $keyFn): array
    {
        $out = [];
        foreach ($items as $it) {
            $k = $keyFn($it);
            if ($k === null) {
                continue;
            }
            $out[$k] = ($out[$k] ?? 0) + 1;
        }

        return $out;
    }

    /** @param array<int,float> $values */
    private function percentile(array $values, float $p): float
    {
        sort($values);
        $idx = (int) ceil($p * count($values)) - 1;
        $idx = max(0, min($idx, count($values) - 1));

        return (float) $values[$idx];
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function bounds(string $periodYm): array
    {
        $start = Carbon::createFromFormat('Y-m', $periodYm)->startOfMonth();

        return [$start, (clone $start)->addMonth()];
    }
}

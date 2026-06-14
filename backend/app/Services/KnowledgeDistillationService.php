<?php

namespace App\Services;

use App\Models\AiRevisionEvent;
use App\Models\Company;
use App\Models\Student;
use App\Models\SupportKnowledge;
use App\Support\AbilityGrowthStage;
use App\Support\PiiMasker;
use App\Support\StudentCohort;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 支援知蒸留 D4: 法人内のデータを条件(対象コホート×成長段階)別に蒸留して支援知(L3)を作る。
 *
 *  - スコープ: 法人(company)内のみ(全国横断は法務クリア後)。
 *  - 同意: 施設の集計同意(ai_consent_aggregate) かつ 児童の学習同意(ai_consent_learning)。
 *  - k-匿名: 同条件の児童が5名未満の条件は保存しない。
 *  - PII安全: 実施傾向はコード+件数、成果は平均、見本抜粋は施設マスカー+スクラブでマスク。
 *  - 冪等: 法人単位で delete → insert。
 */
class KnowledgeDistillationService
{
    public const K = 5;

    public function __construct(private OutcomeService $outcome, private WritingProfileService $profiles) {}

    /** 全法人(集計同意あり)を再計算する。 */
    public function rebuildAll(): int
    {
        $total = 0;
        Company::where('ai_consent_aggregate', true)->pluck('id')->each(function ($cid) use (&$total) {
            $total += $this->rebuild((int) $cid);
        });

        return $total;
    }

    /** 指定法人の支援知を再計算する。戻り値=保存した条件数。 */
    public function rebuild(int $companyId): int
    {
        $company = Company::find($companyId);
        if (! $company || ! $company->ai_consent_aggregate) {
            SupportKnowledge::where('company_id', $companyId)->delete();

            return 0; // 施設が集計同意していなければ蒸留しない(fail-closed)
        }

        $students = Student::whereHas('classroom', fn ($q) => $q->where('company_id', $companyId))
            ->where('ai_consent_learning', true)
            ->whereIn('status', ['active', 'trial', 'short_term'])
            ->get(['id', 'grade_level', 'birth_date', 'grade_adjustment']);

        $groups = $students->groupBy(fn ($s) => StudentCohort::forStudent($s).'|'.AbilityGrowthStage::forStudent($s));
        $masker = $this->profiles->companyMasker($companyId);

        $rows = [];
        foreach ($groups as $key => $members) {
            if ($members->count() < self::K) {
                continue; // k-匿名
            }
            [$cohort, $stage] = explode('|', $key);
            $rows[] = $this->distillGroup($companyId, $cohort, $stage, $members, $masker);
        }

        DB::transaction(function () use ($companyId, $rows) {
            SupportKnowledge::where('company_id', $companyId)->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('support_knowledge')->insert($chunk);
            }
        });

        return count($rows);
    }

    /** @param  \Illuminate\Support\Collection<int,Student>  $members */
    private function distillGroup(int $companyId, string $cohort, string $stage, $members, PiiMasker $masker): array
    {
        $sids = $members->pluck('id');
        $revs = AiRevisionEvent::where('company_id', $companyId)->whereIn('student_id', $sids)
            ->where('changed', true)->whereNotNull('after_text')
            ->orderByDesc('id')->limit(500)
            ->get(['support_category', 'program_category_id', 'section_key', 'after_text', 'edit_kind', 'exemplar_status']);

        $topSupport = $revs->whereNotNull('support_category')->groupBy('support_category')
            ->map->count()->sortDesc()->take(5)
            ->map(fn ($c, $code) => ['code' => $code, 'count' => $c])->values()->all();
        $topPrograms = $revs->whereNotNull('program_category_id')->groupBy('program_category_id')
            ->map->count()->sortDesc()->take(5)
            ->map(fn ($c, $pid) => ['program_category_id' => (int) $pid, 'count' => $c])->values()->all();

        // 成果(outcome)平均
        $od = [];
        $mp = [];
        $ag = [];
        foreach ($members as $m) {
            $o = $this->outcome->forStudent($m);
            if (($o['objective_delta']['has'] ?? false) && $o['objective_delta']['avg_change'] !== null) {
                $od[] = $o['objective_delta']['avg_change'];
            }
            if ($o['monitoring']['has'] ?? false) {
                $mp[] = $o['monitoring']['pct'];
            }
            if ($o['agreement']['has'] ?? false) {
                $ag[] = $o['agreement']['overall'];
            }
        }

        // 見本抜粋(採用見本 or 確定済み、マスク済)
        $excerpts = $revs->filter(fn ($r) => $r->exemplar_status === 'adopted' || in_array($r->edit_kind, ['official', 'submit', 'publish'], true))
            ->map(fn ($r) => [
                'section' => $r->section_key,
                'text' => mb_substr(PiiMasker::scrubStructuredPii(trim($masker->mask((string) $r->after_text))), 0, 140),
            ])
            ->filter(fn ($e) => $e['text'] !== '')->unique('text')->take(4)->values()->all();

        return [
            'company_id' => $companyId,
            'cohort' => $cohort,
            'growth_stage' => $stage,
            'sample_n' => $members->count(),
            'top_support_categories' => json_encode($topSupport, JSON_UNESCAPED_UNICODE),
            'top_programs' => json_encode($topPrograms),
            'outcome_objective_delta_avg' => $od ? round(array_sum($od) / count($od), 2) : null,
            'outcome_monitoring_pct_avg' => $mp ? (int) round(array_sum($mp) / count($mp)) : null,
            'outcome_agreement_avg' => $ag ? (int) round(array_sum($ag) / count($ag)) : null,
            'exemplar_excerpts' => json_encode($excerpts, JSON_UNESCAPED_UNICODE),
            'exemplar_count' => count($excerpts),
            'computed_at' => Carbon::now(),
        ];
    }
}

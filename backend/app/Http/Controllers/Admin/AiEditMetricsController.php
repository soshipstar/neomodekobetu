<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiEditMetric;
use App\Models\AiEditReasonCategory;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\ProgramCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AI学習基盤 S4d: 管理者レポート(Layer2集計 ai_edit_metrics の閲覧)。
 *
 * 施設管理者は自施設、マスターは全社。k匿名は集計時に適用済み(小セルは保存されない)。
 * 職員評価は「修正回数の多寡」ではなく傾向把握に使う前提(画面側でも明示)。
 *
 * 分類: api
 */
class AiEditMetricsController extends Controller
{
    private const FACETS = ['company', 'classroom', 'cohort', 'growth_stage', 'author', 'document_type', 'support_category', 'program_category'];

    private const COHORT_LABELS = [
        'preschool' => '未就学児', 'elementary' => '小学生', 'junior_high' => '中学生', 'high_school' => '高校生', 'other' => 'その他',
    ];

    private const DOC_LABELS = [
        'support_plan' => '個別支援計画', 'monitoring' => 'モニタリング',
        'assessment_staff' => 'アセスメント(職員)', 'assessment_guardian' => 'アセスメント(保護者)',
        'integrated_note' => '連絡帳',
    ];

    private const DOMAIN_LABELS = [
        'health_life' => '健康・生活', 'motor_sensory' => '運動・感覚', 'cognitive_behavior' => '認知・行動',
        'language_communication' => '言語・コミュニケーション', 'social_relations' => '人間関係・社会性',
    ];

    /** GET /api/admin/ai-edit-metrics?period=YYYY-MM&facet=cohort */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => 'このレポートを閲覧する権限がありません。'], 403);
        }

        $facet = $request->input('facet', 'company');
        if (! in_array($facet, self::FACETS, true)) {
            return response()->json(['success' => false, 'message' => '不正な集計軸です。'], 422);
        }
        $period = $request->input('period') ?: Carbon::now()->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            return response()->json(['success' => false, 'message' => 'period は YYYY-MM 形式です。'], 422);
        }

        $isMaster = $user->isMasterAdmin();
        $companyId = $user->company_id;
        if (! $isMaster && ! $companyId) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        $scoped = fn ($q) => $isMaster ? $q : $q->where('company_id', $companyId);

        $rows = $scoped(AiEditMetric::where('period_ym', $period)->where('facet', $facet))
            ->orderByDesc('revision_count')->get();

        $periods = $scoped(AiEditMetric::query())->select('period_ym')->distinct()
            ->orderByDesc('period_ym')->pluck('period_ym')->all();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'facet' => $facet,
                'periods' => $periods,
                'rows' => $this->enrich($rows, $facet),
            ],
        ]);
    }

    /** @param  \Illuminate\Support\Collection<int,AiEditMetric>  $rows */
    private function enrich($rows, string $facet): array
    {
        // 動的ラベル(DB参照)はバッチ解決
        $companyNames = $facet === 'company' ? Company::whereIn('id', $rows->pluck('company_id'))->pluck('name', 'id') : collect();
        $classroomNames = $facet === 'classroom' ? Classroom::whereIn('id', $rows->pluck('classroom_id')->filter())->pluck('classroom_name', 'id') : collect();
        $userNames = $facet === 'author' ? User::whereIn('id', $rows->pluck('author_user_id')->filter())->pluck('full_name', 'id') : collect();
        $programLabels = $facet === 'program_category' ? ProgramCategory::whereIn('id', $rows->pluck('program_category_id')->filter())->pluck('label_ja', 'id') : collect();

        // 全行の top_reason カテゴリをまとめて解決
        $reasonIds = $rows->flatMap(fn ($r) => collect($r->top_reason_categories ?? [])->pluck('category_id'))->filter()->unique();
        $reasonLabels = $reasonIds->isEmpty() ? collect() : AiEditReasonCategory::whereIn('id', $reasonIds)->pluck('label_ja', 'id');

        // D3: 記入者facetは成長レベル(育成指標)を付与する
        $levels = collect();
        if ($facet === 'author') {
            $svc = new \App\Services\SupporterLevelService();
            $levels = $rows->pluck('author_user_id')->filter()->unique()
                ->mapWithKeys(fn ($uid) => [$uid => $svc->levelFor((int) $uid)]);
        }

        return $rows->map(function (AiEditMetric $r) use ($facet, $companyNames, $classroomNames, $userNames, $programLabels, $reasonLabels, $levels) {
            [$dimValue, $label] = $this->dimLabel($r, $facet, $companyNames, $classroomNames, $userNames, $programLabels);
            $topReasons = collect($r->top_reason_categories ?? [])->map(fn ($t) => [
                'category_id' => $t['category_id'] ?? null,
                'label' => $reasonLabels[$t['category_id'] ?? null] ?? '(その他)',
                'count' => $t['count'] ?? 0,
            ])->all();
            $lv = $facet === 'author' ? ($levels[$r->author_user_id] ?? null) : null;

            return [
                'dim_value' => $dimValue,
                'label' => $label,
                'level' => $lv['level'] ?? null,
                'level_label' => $lv['label'] ?? null,
                'distinct_students' => $r->distinct_students,
                'gen_count' => $r->gen_count,
                'revision_count' => $r->revision_count,
                'edited_document_count' => $r->edited_document_count,
                'edit_rate' => $r->edit_rate,
                'ai_acceptance' => $r->ai_acceptance,
                'change_ratio_avg' => $r->change_ratio_avg,
                'change_ratio_p50' => $r->change_ratio_p50,
                'change_ratio_p90' => $r->change_ratio_p90,
                'top_reasons' => $topReasons,
            ];
        })->all();
    }

    /** @return array{0:mixed,1:string} [dim_value, label] */
    private function dimLabel(AiEditMetric $r, string $facet, $companyNames, $classroomNames, $userNames, $programLabels): array
    {
        return match ($facet) {
            'company' => [$r->company_id, $companyNames[$r->company_id] ?? "施設#{$r->company_id}"],
            'classroom' => [$r->classroom_id, $classroomNames[$r->classroom_id] ?? "教室#{$r->classroom_id}"],
            'author' => [$r->author_user_id, $userNames[$r->author_user_id] ?? "職員#{$r->author_user_id}"],
            'program_category' => [$r->program_category_id, $programLabels[$r->program_category_id] ?? "プログラム#{$r->program_category_id}"],
            'cohort' => [$r->subj_cohort, self::COHORT_LABELS[$r->subj_cohort] ?? (string) $r->subj_cohort],
            'growth_stage' => [$r->subj_growth_stage, (string) $r->subj_growth_stage],
            'document_type' => [$r->document_type, self::DOC_LABELS[$r->document_type] ?? (string) $r->document_type],
            'support_category' => [$r->support_category, self::DOMAIN_LABELS[$r->support_category] ?? (string) $r->support_category],
            default => [null, ''],
        };
    }
}

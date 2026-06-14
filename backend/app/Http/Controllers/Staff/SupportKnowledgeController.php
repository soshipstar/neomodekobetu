<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProgramCategory;
use App\Models\Student;
use App\Models\SupportKnowledge;
use App\Support\AbilityGrowthStage;
use App\Support\StudentCohort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 支援知蒸留 D5: 横断検索・根拠提示(法人内)。
 *
 * 児童の条件(対象コホート×成長段階)に合致する法人内の支援知を返す。
 * 「同じような児童 N名にどんな支援が実施され、成果はどうか」を根拠付きで提示。
 *
 * 分類: api
 */
class SupportKnowledgeController extends Controller
{
    private const COHORT_LABELS = [
        'preschool' => '未就学児', 'elementary' => '小学生', 'junior_high' => '中学生', 'high_school' => '高校生', 'other' => 'その他',
    ];

    private const DOMAIN_LABELS = [
        'health_life' => '健康・生活', 'motor_sensory' => '運動・感覚', 'cognitive_behavior' => '認知・行動',
        'language_communication' => '言語・コミュニケーション', 'social_relations' => '人間関係・社会性',
    ];

    /** GET /api/staff/students/{student}/knowledge : この児童に近い条件の支援知(法人内) */
    public function forStudent(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request->user(), $student);
        $student->loadMissing('classroom');
        $companyId = $student->classroom?->company_id;
        if (! $companyId) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $cohort = StudentCohort::forStudent($student);
        $stage = AbilityGrowthStage::forStudent($student);
        $k = SupportKnowledge::where('company_id', $companyId)->where('cohort', $cohort)->where('growth_stage', $stage)->first();
        if (! $k) {
            return response()->json(['success' => true, 'data' => null]); // 同条件の蓄積なし(k匿名等)
        }

        $progNames = ProgramCategory::whereIn('id', collect($k->top_programs)->pluck('program_category_id'))->pluck('label_ja', 'id');

        return response()->json(['success' => true, 'data' => [
            'cohort' => $cohort,
            'cohort_label' => self::COHORT_LABELS[$cohort] ?? $cohort,
            'growth_stage' => $stage,
            'sample_n' => $k->sample_n,
            'top_support_categories' => collect($k->top_support_categories ?? [])->map(fn ($t) => [
                'label' => self::DOMAIN_LABELS[$t['code']] ?? $t['code'], 'count' => $t['count'],
            ])->all(),
            'top_programs' => collect($k->top_programs ?? [])->map(fn ($t) => [
                'label' => $progNames[$t['program_category_id']] ?? 'プログラム', 'count' => $t['count'],
            ])->all(),
            'outcome' => [
                'objective_delta_avg' => $k->outcome_objective_delta_avg,
                'monitoring_pct_avg' => $k->outcome_monitoring_pct_avg,
                'agreement_avg' => $k->outcome_agreement_avg,
            ],
            'exemplar_excerpts' => $k->exemplar_excerpts ?? [],
            'computed_at' => $k->computed_at,
        ]]);
    }

    private function authorizeStudent($user, Student $student): void
    {
        if ($user->classroom_id && ! in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この児童へのアクセス権限がありません。');
        }
    }
}

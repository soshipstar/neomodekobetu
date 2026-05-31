<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 連絡帳系の各「生徒選択リスト」画面 (アセスメント職員/保護者・個別支援計画・
 * モニタリング表) で使う、生徒の概要 (最新作成日・アラート・学年) を一括取得
 * するエンドポイント。
 *
 * 各画面が個別にループ呼び出しすると N+1 になるため、ここで集約クエリで返す。
 */
class StudentOverviewController extends Controller
{
    /**
     * GET /api/staff/students-overview/{kind}
     *
     * kind ∈ { assessment-staff, assessment-guardian, support-plan, monitoring }
     *
     * 返却: [
     *   { id, student_name, grade_level, latest_date, has_alert, alert_label, subtitle }
     * ]
     */
    public function show(Request $request, string $kind): JsonResponse
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleClassroomIds();

        // 在籍生徒一覧 (各画面の母集団)
        $students = Student::query()
            ->whereIn('classroom_id', $accessibleIds ?: [0])
            ->where('is_active', true)
            ->where('status', 'active')
            // ふりがな優先で50音順（未設定は漢字氏名でフォールバック）
            ->orderBy('student_name_kana')
            ->orderBy('student_name')
            ->get(['id', 'student_name', 'student_name_kana', 'grade_level'])
            ->keyBy('id');

        $studentIds = $students->keys()->all();
        if (empty($studentIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $today = Carbon::today();

        $rows = match ($kind) {
            'assessment-staff'    => $this->forAssessmentStaff($studentIds, $today),
            'assessment-guardian' => $this->forAssessmentGuardian($studentIds, $today),
            'support-plan'        => $this->forSupportPlan($studentIds),
            'monitoring'          => $this->forMonitoring($studentIds, $today),
            default               => null,
        };

        if ($rows === null) {
            return response()->json([
                'success' => false,
                'message' => '未対応の種別です: ' . $kind,
            ], 422);
        }

        $data = $students->map(function ($s) use ($rows) {
            $entry = $rows[$s->id] ?? [
                'latest_date'  => null,
                'has_alert'    => false,
                'alert_label'  => null,
                'subtitle'     => null,
            ];
            return [
                'id'                => $s->id,
                'student_name'      => $s->student_name,
                'student_name_kana' => $s->student_name_kana,
                'grade_level'       => $s->grade_level,
                ...$entry,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * アセスメント職員: 最新の assessment_periods.start_date と、
     * 同 period に staff_entries が無い (= 未入力) もしくは未提出 (is_submitted=false)
     * かつ submission_deadline を過ぎていれば「期限切れ」アラート、
     * 期限が今日から 7 日以内なら「期限間近」。
     */
    private function forAssessmentStaff(array $studentIds, Carbon $today): array
    {
        $rows = DB::table('assessment_periods as p')
            ->leftJoin('assessment_staff as s', function ($j) {
                $j->on('s.period_id', '=', 'p.id')->on('s.student_id', '=', 'p.student_id');
            })
            ->whereIn('p.student_id', $studentIds)
            ->where('p.is_active', true)
            ->select([
                'p.student_id',
                DB::raw('MAX(p.start_date) as latest_start'),
                DB::raw('SUM(CASE WHEN (s.is_submitted IS NULL OR s.is_submitted = false) AND p.submission_deadline < CURRENT_DATE THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN (s.is_submitted IS NULL OR s.is_submitted = false) AND p.submission_deadline BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL \'7 day\' THEN 1 ELSE 0 END) as urgent_count'),
            ])
            ->groupBy('p.student_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->student_id] = [
                'latest_date' => $row->latest_start,
                'has_alert'   => ($row->overdue_count > 0) || ($row->urgent_count > 0),
                'alert_label' => $row->overdue_count > 0 ? '期限切れ' : ($row->urgent_count > 0 ? '期限間近' : null),
                'subtitle'    => $row->latest_start ? "最新期間: {$row->latest_start}" : null,
            ];
        }
        return $result;
    }

    /**
     * アセスメント保護者: 同様だが assessment_guardian で判定。
     */
    private function forAssessmentGuardian(array $studentIds, Carbon $today): array
    {
        $rows = DB::table('assessment_periods as p')
            ->leftJoin('assessment_guardian as g', function ($j) {
                $j->on('g.period_id', '=', 'p.id')->on('g.student_id', '=', 'p.student_id');
            })
            ->whereIn('p.student_id', $studentIds)
            ->where('p.is_active', true)
            ->select([
                'p.student_id',
                DB::raw('MAX(p.start_date) as latest_start'),
                DB::raw('SUM(CASE WHEN (g.is_submitted IS NULL OR g.is_submitted = false) AND p.submission_deadline < CURRENT_DATE THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN (g.is_submitted IS NULL OR g.is_submitted = false) AND p.submission_deadline BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL \'7 day\' THEN 1 ELSE 0 END) as urgent_count'),
            ])
            ->groupBy('p.student_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->student_id] = [
                'latest_date' => $row->latest_start,
                'has_alert'   => ($row->overdue_count > 0) || ($row->urgent_count > 0),
                'alert_label' => $row->overdue_count > 0 ? '期限切れ' : ($row->urgent_count > 0 ? '期限間近' : null),
                'subtitle'    => $row->latest_start ? "最新期間: {$row->latest_start}" : null,
            ];
        }
        return $result;
    }

    /**
     * 個別支援計画: 最新の created_date と、署名待ち (is_official=true,
     * guardian_confirmed=false) または公式版未作成のアラート。
     */
    private function forSupportPlan(array $studentIds): array
    {
        $rows = DB::table('individual_support_plans')
            ->whereIn('student_id', $studentIds)
            ->where(function ($q) {
                $q->where('is_hidden', false)->orWhereNull('is_hidden');
            })
            ->select([
                'student_id',
                DB::raw('MAX(created_date) as latest_date'),
                DB::raw('SUM(CASE WHEN is_official = true AND (guardian_confirmed IS NULL OR guardian_confirmed = false) THEN 1 ELSE 0 END) as awaiting_sig_count'),
                DB::raw('SUM(CASE WHEN is_official = true THEN 1 ELSE 0 END) as official_count'),
            ])
            ->groupBy('student_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $hasAwaiting = $row->awaiting_sig_count > 0;
            $hasOfficial = $row->official_count > 0;
            $result[$row->student_id] = [
                'latest_date' => $row->latest_date,
                'has_alert'   => $hasAwaiting || ! $hasOfficial,
                'alert_label' => $hasAwaiting ? '署名待ち' : (! $hasOfficial ? '未交付' : null),
                'subtitle'    => $row->latest_date ? "最新作成日: {$row->latest_date}" : null,
            ];
        }
        return $result;
    }

    /**
     * モニタリング: 最新の monitoring_date と、公式版未確認のアラート。
     */
    private function forMonitoring(array $studentIds, Carbon $today): array
    {
        $rows = DB::table('monitoring_records')
            ->whereIn('student_id', $studentIds)
            ->select([
                'student_id',
                DB::raw('MAX(monitoring_date) as latest_date'),
                DB::raw('SUM(CASE WHEN is_official = true AND (guardian_confirmed IS NULL OR guardian_confirmed = false) THEN 1 ELSE 0 END) as awaiting_sig_count'),
                DB::raw('MAX(monitoring_date) as max_monitoring_date'),
            ])
            ->groupBy('student_id')
            ->get();

        // 6ヶ月以上モニタリングが無い児童は「要モニタリング」アラート
        $sixMonthsAgo = $today->copy()->subMonths(6)->toDateString();

        $result = [];
        foreach ($rows as $row) {
            $hasAwaiting = $row->awaiting_sig_count > 0;
            $isOverdue = ! $row->max_monitoring_date || $row->max_monitoring_date < $sixMonthsAgo;
            $result[$row->student_id] = [
                'latest_date' => $row->latest_date,
                'has_alert'   => $hasAwaiting || $isOverdue,
                'alert_label' => $hasAwaiting ? '署名待ち' : ($isOverdue ? '要モニタリング' : null),
                'subtitle'    => $row->latest_date ? "最新: {$row->latest_date}" : 'モニタリング未実施',
            ];
        }

        // モニタリング行が一切ない生徒は「要モニタリング」
        foreach ($studentIds as $sid) {
            if (! isset($result[$sid])) {
                $result[$sid] = [
                    'latest_date' => null,
                    'has_alert'   => true,
                    'alert_label' => '要モニタリング',
                    'subtitle'    => 'モニタリング未実施',
                ];
            }
        }

        return $result;
    }
}

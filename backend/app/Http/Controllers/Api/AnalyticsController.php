<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * 生徒の成長データを取得（5領域の時系列推移）
     */
    public function studentGrowth(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $months = $request->integer('months', 6);
        $startDate = now()->subMonths($months)->toDateString();

        // 月別 x 5領域の記録件数を集計
        $domainStats = StudentRecord::where('student_id', $student->id)
            ->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '>=', $startDate))
            ->join('daily_records', 'student_records.daily_record_id', '=', 'daily_records.id')
            ->select(
                DB::raw("DATE_FORMAT(daily_records.record_date, '%Y-%m') as month"),
                'student_records.domain1',
                DB::raw('COUNT(*) as record_count')
            )
            ->groupBy('month', 'student_records.domain1')
            ->orderBy('month')
            ->get();

        // モニタリング達成状況の推移
        $monitoringProgress = MonitoringRecord::where('student_id', $student->id)
            ->with('details')
            ->where('monitoring_date', '>=', $startDate)
            ->orderBy('monitoring_date')
            ->get()
            ->map(function ($record) {
                $totalDetails = $record->details->count();
                $achieved = $record->details->where('achievement_status', '達成')->count();

                return [
                    'date'             => $record->monitoring_date,
                    'total_goals'      => $totalDetails,
                    'achieved_goals'   => $achieved,
                    'achievement_rate' => $totalDetails > 0 ? round(($achieved / $totalDetails) * 100, 1) : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'student'              => $student->only(['id', 'student_name']),
                'domain_stats'         => $domainStats,
                'monitoring_progress'  => $monitoringProgress,
            ],
        ]);
    }

    /**
     * 施設評価の分析データ
     */
    public function facilityEvaluation(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // 年度ごとの回答率推移
        $periodStats = DB::table('facility_evaluation_periods as p')
            ->when($classroomId, fn ($q) => $q->where('p.classroom_id', $classroomId))
            ->leftJoin('facility_guardian_evaluations as e', function ($join) {
                $join->on('e.period_id', '=', 'p.id')
                     ->where('e.is_submitted', true);
            })
            ->select(
                'p.id',
                'p.fiscal_year',
                'p.title',
                DB::raw('COUNT(DISTINCT e.id) as response_count')
            )
            ->groupBy('p.id', 'p.fiscal_year', 'p.title')
            ->orderBy('p.fiscal_year')
            ->get();

        // 最新期間のカテゴリ別「はい」率
        $latestPeriod = DB::table('facility_evaluation_periods')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->orderByDesc('fiscal_year')
            ->first();

        $categorySummary = [];
        if ($latestPeriod) {
            $categorySummary = DB::table('facility_guardian_evaluation_answers as a')
                ->join('facility_guardian_evaluations as e', 'a.evaluation_id', '=', 'e.id')
                ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
                ->where('e.period_id', $latestPeriod->id)
                ->where('e.is_submitted', true)
                ->select(
                    'q.category',
                    DB::raw("ROUND(SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as yes_rate"),
                    DB::raw('COUNT(*) as total_answers')
                )
                ->groupBy('q.category')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'period_stats'     => $periodStats,
                'category_summary' => $categorySummary,
                'latest_period'    => $latestPeriod,
            ],
        ]);
    }

    /**
     * 出欠統計データ
     */
    public function attendanceStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $months = $request->integer('months', 3);
        $startDate = now()->subMonths($months)->toDateString();

        // 月別欠席数
        $monthlyAbsences = AbsenceNotification::whereHas('student', function ($q) use ($classroomId) {
            if ($classroomId) {
                $q->where('classroom_id', $classroomId);
            }
        })
            ->where('absence_date', '>=', $startDate)
            ->select(
                DB::raw("DATE_FORMAT(absence_date, '%Y-%m') as month"),
                DB::raw('COUNT(*) as absence_count'),
                DB::raw("SUM(CASE WHEN makeup_status = 'approved' THEN 1 ELSE 0 END) as makeup_count")
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 生徒別欠席ランキング
        $studentAbsences = AbsenceNotification::whereHas('student', function ($q) use ($classroomId) {
            if ($classroomId) {
                $q->where('classroom_id', $classroomId);
            }
        })
            ->where('absence_date', '>=', $startDate)
            ->select('student_id', DB::raw('COUNT(*) as absence_count'))
            ->groupBy('student_id')
            ->orderByDesc('absence_count')
            ->limit(10)
            ->with('student:id,student_name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'monthly_absences'  => $monthlyAbsences,
                'student_absences'  => $studentAbsences,
            ],
        ]);
    }

    /**
     * 支援計画の有効性分析
     */
    public function supportPlanEffectiveness(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // 計画の完了率
        $planStats = IndividualSupportPlan::query()
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->select(
                DB::raw('COUNT(*) as total_plans'),
                DB::raw("SUM(CASE WHEN status = 'official' THEN 1 ELSE 0 END) as official_plans"),
                DB::raw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_plans"),
                DB::raw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_plans"),
                DB::raw('SUM(CASE WHEN guardian_reviewed_at IS NOT NULL THEN 1 ELSE 0 END) as guardian_reviewed')
            )
            ->first();

        // モニタリング達成率の分布
        $achievementDistribution = DB::table('monitoring_details as md')
            ->join('monitoring_records as mr', 'md.monitoring_id', '=', 'mr.id')
            ->when($classroomId, fn ($q) => $q->where('mr.classroom_id', $classroomId))
            ->whereNotNull('md.achievement_status')
            ->select(
                'md.achievement_status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('md.achievement_status')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'plan_stats'                => $planStats,
                'achievement_distribution'  => $achievementDistribution,
            ],
        ]);
    }
}

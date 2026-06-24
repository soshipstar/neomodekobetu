<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceResponseRecord;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 月次利用日数（連絡帳ベース）＋ 欠席時対応加算の集計。
 *
 * 管理画面で施設(教室)を選び、年月ごとに児童別の
 *  - 利用日数 = student_records を持つ daily_records.record_date の異なる日付数
 *  - 欠席時対応加算 記録件数 / 算定回数(上限 月4回/児童)
 * を一覧する。算定回数は施設の absence_addition_enabled が OFF のとき 0。
 *
 * 分類: api(logic)
 */
class MonthlyUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'year'         => 'required|integer|min:2000|max:2100',
            'month'        => 'required|integer|min:1|max:12',
        ]);

        $classroomId = (int) $validated['classroom_id'];

        // アクセス制御: master 以外はアクセス可能な教室のみ
        if (! $user->is_master && ! in_array($classroomId, $user->accessibleClassroomIds(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'この事業所を閲覧する権限がありません。',
            ], 403);
        }

        $classroom = Classroom::findOrFail($classroomId);
        $additionEnabled = (bool) $classroom->absence_addition_enabled;

        $start = Carbon::create((int) $validated['year'], (int) $validated['month'], 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];

        // 利用日数（連絡帳ベース）= student_records を持つ record_date の異なる日数
        $usageByStudent = StudentRecord::query()
            ->join('daily_records', 'student_records.daily_record_id', '=', 'daily_records.id')
            ->where('daily_records.classroom_id', $classroomId)
            ->whereBetween('daily_records.record_date', $range)
            ->groupBy('student_records.student_id')
            ->selectRaw('student_records.student_id as student_id, COUNT(DISTINCT daily_records.record_date) as usage_days')
            ->pluck('usage_days', 'student_id');

        // 欠席時対応加算の記録件数（児童別）
        $additionByStudent = AbsenceResponseRecord::query()
            ->where('classroom_id', $classroomId)
            ->whereBetween('absence_date', $range)
            ->groupBy('student_id')
            ->selectRaw('student_id, COUNT(*) as cnt')
            ->pluck('cnt', 'student_id');

        // 行対象の児童: 在籍児童 ∪ 集計に出た児童(過去在籍など欠落させない)
        $studentIds = collect($usageByStudent->keys())
            ->merge($additionByStudent->keys())
            ->map(fn ($id) => (int) $id)
            ->unique();

        $activeStudents = Student::active()->byClassroom($classroomId)
            ->get(['id', 'student_name', 'grade_level']);
        $activeIds = $activeStudents->pluck('id')->map(fn ($id) => (int) $id);

        $extraIds = $studentIds->diff($activeIds);
        $extraStudents = $extraIds->isNotEmpty()
            ? Student::whereIn('id', $extraIds)->get(['id', 'student_name', 'grade_level'])
            : collect();

        $students = $activeStudents->concat($extraStudents);

        $rows = $students->map(function ($student) use ($usageByStudent, $additionByStudent, $additionEnabled) {
            $records = (int) ($additionByStudent[$student->id] ?? 0);
            return [
                'student_id'       => (int) $student->id,
                'student_name'     => $student->student_name,
                'grade_level'      => $student->grade_level,
                'usage_days'       => (int) ($usageByStudent[$student->id] ?? 0),
                'addition_records' => $additionEnabled ? $records : 0,
                'addition_billable' => $additionEnabled ? min($records, 4) : 0,
            ];
        })
        ->sortBy([['usage_days', 'desc'], ['student_name', 'asc']])
        ->values();

        $totals = [
            'usage_days'        => (int) $rows->sum('usage_days'),
            'addition_records'  => (int) $rows->sum('addition_records'),
            'addition_billable' => (int) $rows->sum('addition_billable'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'classroom_id'             => $classroomId,
                'classroom_name'           => $classroom->classroom_name,
                'year'                     => (int) $validated['year'],
                'month'                    => (int) $validated['month'],
                'absence_addition_enabled' => $additionEnabled,
                'rows'                     => $rows,
                'totals'                   => $totals,
            ],
        ]);
    }
}

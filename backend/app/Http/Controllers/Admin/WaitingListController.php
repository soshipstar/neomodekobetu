<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitingListController extends Controller
{
    /**
     * 待機リスト（status=waiting の生徒一覧）を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Student::where('status', 'waiting')
            ->with(['guardian:id,full_name,email']);

        // スタッフは自教室のみ
        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        } elseif ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $students = $query->orderBy('created_at')->get();

        $data = $students->map(function ($s) {
            return [
                'id'              => $s->id,
                'student_name'    => $s->student_name,
                'birth_date'      => $s->birth_date,
                'guardian_name'   => $s->guardian?->full_name ?? '-',
                'guardian_email'  => $s->guardian?->email,
                'desired_start_date'   => $s->desired_start_date,
                'desired_weekly_count' => $s->desired_weekly_count,
                'waiting_notes'        => $s->waiting_notes,
                'desired_monday'       => $s->desired_monday,
                'desired_tuesday'      => $s->desired_tuesday,
                'desired_wednesday'    => $s->desired_wednesday,
                'desired_thursday'     => $s->desired_thursday,
                'desired_friday'       => $s->desired_friday,
                'desired_saturday'     => $s->desired_saturday,
                'desired_sunday'       => $s->desired_sunday,
                'status'               => $s->status,
                'created_at'           => $s->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * 曜日別の待機数・在籍利用者数サマリーを返す
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $result = [];

        foreach ($days as $day) {
            $waitingQuery = Student::where('status', 'waiting');
            $activeQuery = Student::where('status', 'active')->where('is_active', true);

            if ($classroomId) {
                $waitingQuery->where('classroom_id', $classroomId);
                $activeQuery->where('classroom_id', $classroomId);
            }

            $result[] = [
                'day'            => $day,
                'waiting_count'  => (clone $waitingQuery)->where("desired_{$day}", true)->count(),
                'active_count'   => (clone $activeQuery)->where("scheduled_{$day}", true)->count(),
            ];
        }

        // 合計
        $totalWaiting = Student::where('status', 'waiting')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'days'          => $result,
                'total_waiting' => $totalWaiting,
            ],
        ]);
    }

    /**
     * 待機生徒の情報を更新（ステータス変更含む）
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'status'               => 'sometimes|string|in:waiting,active',
            'desired_start_date'   => 'nullable|date',
            'desired_weekly_count' => 'nullable|integer|min:1|max:7',
            'waiting_notes'        => 'nullable|string|max:1000',
            'desired_monday'       => 'nullable|boolean',
            'desired_tuesday'      => 'nullable|boolean',
            'desired_wednesday'    => 'nullable|boolean',
            'desired_thursday'     => 'nullable|boolean',
            'desired_friday'       => 'nullable|boolean',
            'desired_saturday'     => 'nullable|boolean',
            'desired_sunday'       => 'nullable|boolean',
        ]);

        // 入所処理: desired → scheduled にコピー
        if (($validated['status'] ?? null) === 'active' && $student->status === 'waiting') {
            $validated['is_active'] = true;
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                $validated["scheduled_{$day}"] = $student->{"desired_{$day}"} ?? false;
            }
        }

        $student->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $student->fresh(),
            'message' => '更新しました。',
        ]);
    }
}

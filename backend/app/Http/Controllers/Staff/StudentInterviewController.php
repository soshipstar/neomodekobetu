<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentInterview;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentInterviewController extends Controller
{
    /**
     * 全生徒の面談記録一覧（生徒ごとにグルーピング）
     */
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Student::with(['interviews' => function ($q) {
            $q->with('interviewer:id,full_name')
              ->orderByDesc('interview_date');
        }]);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $students = $query->where('is_active', true)
            ->orderBy('student_name')
            ->get(['id', 'student_name', 'classroom_id']);

        $data = $students->map(function ($student) {
            return [
                'id'              => $student->id,
                'student_name'    => $student->student_name,
                'interview_count' => $student->interviews->count(),
                'latest_interview' => $student->interviews->first(),
                'interviews'      => $student->interviews,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * 生徒の面接記録一覧を取得
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $interviews = $student->interviews()
            ->with('interviewer:id,full_name')
            ->orderByDesc('interview_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $interviews,
        ]);
    }

    /**
     * 面接記録を新規作成
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $validated = $request->validate([
            'interview_date'       => 'required|date',
            'interview_content'    => 'required|string',
            'child_wish'           => 'nullable|string',
            'check_school'         => 'nullable|boolean',
            'check_school_notes'   => 'nullable|string',
            'check_home'           => 'nullable|boolean',
            'check_home_notes'     => 'nullable|string',
            'check_troubles'       => 'nullable|boolean',
            'check_troubles_notes' => 'nullable|string',
            'other_notes'          => 'nullable|string',
        ]);

        $interview = StudentInterview::create(array_merge($validated, [
            'student_id'     => $student->id,
            'classroom_id'   => $student->classroom_id,
            'interviewer_id' => $request->user()->id,
            'created_by'     => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $interview,
            'message' => '面接記録を作成しました。',
        ], 201);
    }

    /**
     * 面接記録の詳細を取得
     */
    public function showSingle(Request $request, StudentInterview $interview): JsonResponse
    {
        if ($request->user()->classroom_id && $interview->classroom_id !== $request->user()->classroom_id) {
            abort(403);
        }
        $interview->load(['student:id,student_name', 'interviewer:id,full_name']);
        return response()->json(['success' => true, 'data' => $interview]);
    }

    /**
     * 面接記録を更新
     */
    public function update(Request $request, StudentInterview $interview): JsonResponse
    {
        if ($request->user()->classroom_id && $interview->classroom_id !== $request->user()->classroom_id) {
            abort(403, 'アクセス権限がありません。');
        }

        $validated = $request->validate([
            'interview_date'       => 'nullable|date',
            'interview_content'    => 'nullable|string',
            'child_wish'           => 'nullable|string',
            'check_school'         => 'nullable|boolean',
            'check_school_notes'   => 'nullable|string',
            'check_home'           => 'nullable|boolean',
            'check_home_notes'     => 'nullable|string',
            'check_troubles'       => 'nullable|boolean',
            'check_troubles_notes' => 'nullable|string',
            'other_notes'          => 'nullable|string',
        ]);

        $interview->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $interview->fresh('interviewer:id,full_name'),
            'message' => '面談記録を更新しました。',
        ]);
    }

    /**
     * 面接記録を削除
     */
    public function destroy(Request $request, StudentInterview $interview): JsonResponse
    {
        if ($request->user()->classroom_id && $interview->classroom_id !== $request->user()->classroom_id) {
            abort(403, 'アクセス権限がありません。');
        }

        $interview->delete();

        return response()->json([
            'success' => true,
            'message' => '面談記録を削除しました。',
        ]);
    }

    /**
     * 面接記録をPDF出力
     */
    public function pdf(Request $request, StudentInterview $interview)
    {
        $interview->load(['student', 'interviewer:id,full_name']);

        if ($request->user()->classroom_id && $interview->classroom_id !== $request->user()->classroom_id) {
            abort(403, 'アクセス権限がありません。');
        }

        $filename = "student_interview_{$interview->id}.pdf";

        return PuppeteerPdfService::download('pdf.student-interview', [
            'interview' => $interview,
            'student' => $interview->student,
        ], $filename);
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}

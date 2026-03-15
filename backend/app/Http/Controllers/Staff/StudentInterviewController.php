<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentInterview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentInterviewController extends Controller
{
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
        ]));

        return response()->json([
            'success' => true,
            'data'    => $interview,
            'message' => '面接記録を作成しました。',
        ], 201);
    }

    /**
     * 面接記録をPDF出力
     */
    public function pdf(Request $request, StudentInterview $interview): Response
    {
        $interview->load(['student', 'interviewer:id,full_name']);

        if ($request->user()->classroom_id && $interview->classroom_id !== $request->user()->classroom_id) {
            abort(403, 'アクセス権限がありません。');
        }

        $pdf = Pdf::loadView('pdf.student-interview', [
            'interview' => $interview,
            'student' => $interview->student,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'ipag');

        $filename = "student_interview_{$interview->id}.pdf";

        return new Response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}

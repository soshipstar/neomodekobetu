<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SubmissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    /**
     * 提出物一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $query = SubmissionRequest::where('student_id', $student->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->orderByDesc('due_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $submissions,
        ]);
    }

    /**
     * 提出物を提出する
     */
    public function store(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $validated = $request->validate([
            'submission_request_id' => 'required|exists:submission_requests,id',
            'content'               => 'nullable|string',
            'attachment'            => 'nullable|file|max:3072',
        ]);

        $submissionRequest = SubmissionRequest::where('id', $validated['submission_request_id'])
            ->where('student_id', $student->id)
            ->first();

        if (! $submissionRequest) {
            return response()->json([
                'success' => false,
                'message' => '提出物が見つかりません。',
            ], 404);
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('submission_attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
        }

        $submissionRequest->update([
            'status'                       => 'submitted',
            'submitted_at'                 => now(),
            'submitted_content'            => $validated['content'] ?? null,
            'submitted_attachment_path'    => $attachmentPath,
            'submitted_attachment_name'    => $attachmentName,
            'submitted_attachment_size'    => $attachmentSize,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $submissionRequest->fresh(),
            'message' => '提出しました。',
        ]);
    }

    /**
     * 提出物を個別に提出する（ID指定）
     */
    public function submit(Request $request, SubmissionRequest $submissionRequest): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        if ($submissionRequest->student_id !== $student->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'content'    => 'nullable|string',
            'attachment' => 'nullable|file|max:3072',
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('submission_attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
        }

        $submissionRequest->update([
            'status'                       => 'submitted',
            'submitted_at'                 => now(),
            'submitted_content'            => $validated['content'] ?? null,
            'submitted_attachment_path'    => $attachmentPath,
            'submitted_attachment_name'    => $attachmentName,
            'submitted_attachment_size'    => $attachmentSize,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $submissionRequest->fresh(),
            'message' => '提出しました。',
        ]);
    }

    private function getStudent(Request $request): ?Student
    {
        $user = $request->user();

        return Student::where('username', $user->username)->first();
    }
}

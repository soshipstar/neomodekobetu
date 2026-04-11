<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Student;
use App\Models\SubmissionRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StaffSubmissionController extends Controller
{
    /**
     * 提出物一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = SubmissionRequest::with([
            'student:id,student_name',
            'guardian:id,full_name',
            'creator:id,full_name',
        ]);

        $accessibleIds = $user->accessibleClassroomIds();

        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($accessibleIds) {
                $q->whereIn('classroom_id', $accessibleIds);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }

        $submissions = $query
            ->orderBy('is_completed')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($sub) {
                return [
                    'id'                       => $sub->id,
                    'student_id'               => $sub->student_id,
                    'student_name'             => $sub->student?->student_name ?? '',
                    'guardian_name'            => $sub->guardian?->full_name ?? '',
                    'created_by_name'          => $sub->creator?->full_name ?? '',
                    'title'                    => $sub->title,
                    'description'              => $sub->description,
                    'due_date'                 => $sub->due_date?->toDateString(),
                    'is_completed'             => $sub->is_completed,
                    'completed_at'             => $sub->completed_at?->toIso8601String(),
                    'completed_note'           => $sub->completed_note,
                    'attachment_path'          => $sub->attachment_path,
                    'attachment_original_name' => $sub->attachment_original_name,
                    'attachment_size'          => $sub->attachment_size,
                    'created_at'               => $sub->created_at->toIso8601String(),
                ];
            });

        // 統計
        $allForStats = SubmissionRequest::query();
        if ($classroomId) {
            $allForStats->whereHas('student', function ($q) use ($accessibleIds) {
                $q->whereIn('classroom_id', $accessibleIds);
            });
        }
        $statsRaw = $allForStats->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = false THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN is_completed = true AND completed_at >= NOW() - INTERVAL '1 month' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_completed = false AND due_date < CURRENT_DATE THEN 1 ELSE 0 END) as overdue
        ")->first();

        return response()->json([
            'success' => true,
            'data'    => $submissions,
            'stats'   => [
                'total'     => (int) $statsRaw->total,
                'pending'   => (int) $statsRaw->pending,
                'completed' => (int) $statsRaw->completed,
                'overdue'   => (int) $statsRaw->overdue,
            ],
        ]);
    }

    /**
     * 提出物詳細を取得
     */
    public function show(Request $request, SubmissionRequest $submissionRequest): JsonResponse
    {
        $submissionRequest->load(['student:id,student_name', 'guardian:id,full_name', 'creator:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $submissionRequest,
        ]);
    }

    /**
     * 提出物リクエストを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id'  => 'required|exists:students,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date'    => 'nullable|date',
        ]);

        $submission = DB::transaction(function () use ($validated, $user) {
            $submission = SubmissionRequest::create([
                'student_id'  => $validated['student_id'],
                'created_by'  => $user->id,
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date'    => $validated['due_date'] ?? null,
            ]);

            // チャットルームに提出物通知メッセージを挿入（レガシー互換）
            $room = ChatRoom::where('student_id', $validated['student_id'])->first();

            if ($room) {
                $title = $validated['title'];
                $description = $validated['description'] ?? '';
                $dueDate = isset($validated['due_date'])
                    ? Carbon::parse($validated['due_date'])->format('Y年n月j日')
                    : '';

                $notificationMessage = "【提出期限のお知らせ】\n\n件名: {$title}\n";
                if ($description) {
                    $notificationMessage .= "詳細: {$description}\n";
                }
                if ($dueDate) {
                    $notificationMessage .= "提出期限: {$dueDate}";
                }

                ChatMessage::create([
                    'room_id'      => $room->id,
                    'sender_id'    => $user->id,
                    'sender_type'  => 'staff',
                    'message'      => $notificationMessage,
                    'message_type' => 'normal',
                ]);

                $room->update(['last_message_at' => now()]);
            }

            return $submission;
        });

        // 生徒の保護者に通知（in-app + Web Push）
        $student = Student::find($validated['student_id']);
        if ($student && $student->guardian_id) {
            $guardian = User::find($student->guardian_id);
            if ($guardian) {
                $body = $validated['title'];
                if (!empty($validated['due_date'])) {
                    $body .= ' (期限: ' . Carbon::parse($validated['due_date'])->format('Y年n月j日') . ')';
                }
                app(NotificationService::class)->notify(
                    $guardian,
                    'submission_request',
                    '提出物の依頼が届きました',
                    $body,
                    ['url' => '/guardian/dashboard', 'submission_id' => $submission->id],
                );
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $submission,
            'message' => '提出物リクエストを作成しました。',
        ], 201);
    }

    /**
     * 提出物リクエストを更新
     */
    public function update(Request $request, SubmissionRequest $submissionRequest): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'due_date'       => 'nullable|date',
            'is_completed'   => 'boolean',
            'completed_note' => 'nullable|string|max:1000',
        ]);

        // 完了にする場合
        if (isset($validated['is_completed']) && $validated['is_completed'] && ! $submissionRequest->is_completed) {
            $validated['completed_at'] = now();
        }

        // 未提出に戻す場合
        if (isset($validated['is_completed']) && ! $validated['is_completed'] && $submissionRequest->is_completed) {
            $validated['completed_at'] = null;
            $validated['completed_note'] = null;
        }

        $submissionRequest->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $submissionRequest->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * 提出物リクエストを削除
     */
    public function destroy(Request $request, SubmissionRequest $submissionRequest): JsonResponse
    {
        $submissionRequest->delete();

        return response()->json([
            'success' => true,
            'message' => '提出物リクエストを削除しました。',
        ]);
    }

    /**
     * 提出物に紐づく生徒一覧を取得
     */
    public function students(Request $request, SubmissionRequest $submissionRequest): JsonResponse
    {
        // 同じ提出物リクエストのタイトルで関連する生徒を取得
        $students = \App\Models\Student::whereHas('submissionRequests', function ($q) use ($submissionRequest) {
            $q->where('title', $submissionRequest->title)
              ->where('due_date', $submissionRequest->due_date);
        })->get(['id', 'student_name', 'classroom_id', 'grade_level']);

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\SubmissionRequest;
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
        ]);

        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }

        $submissions = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $submissions,
        ]);
    }

    /**
     * 提出物詳細を取得
     */
    public function show(Request $request, SubmissionRequest $submissionRequest): JsonResponse
    {
        $submissionRequest->load(['student:id,student_name', 'guardian:id,full_name']);

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

        if (isset($validated['is_completed']) && $validated['is_completed'] && ! $submissionRequest->is_completed) {
            $validated['completed_at'] = now();
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

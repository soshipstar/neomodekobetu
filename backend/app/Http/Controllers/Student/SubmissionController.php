<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Student;
use App\Models\StudentSubmission;
use App\Models\SubmissionRequest;
use App\Models\WeeklyPlanSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    /**
     * 提出物一覧を取得（3ソース統合: weekly_plan, guardian_chat, student）
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $allSubmissions = [];

        // 1. 週間計画表の提出物
        $weeklyPlanSubs = WeeklyPlanSubmission::whereHas('plan', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->get();

        foreach ($weeklyPlanSubs as $sub) {
            $allSubmissions[] = [
                'id'           => $sub->id,
                'title'        => $sub->submission_item,
                'description'  => '',
                'due_date'     => $sub->due_date?->toDateString(),
                'is_completed' => $sub->is_completed,
                'completed_at' => $sub->completed_at?->toIso8601String(),
                'source'       => 'weekly_plan',
            ];
        }

        // 2. 保護者チャット経由の提出物（submission_requests via chat_rooms）
        $chatRoomIds = ChatRoom::where('student_id', $student->id)->pluck('id');
        if ($chatRoomIds->isNotEmpty()) {
            $chatSubs = SubmissionRequest::whereIn('room_id', $chatRoomIds)->get();
            foreach ($chatSubs as $sub) {
                $allSubmissions[] = [
                    'id'                       => $sub->id,
                    'title'                    => $sub->title,
                    'description'              => $sub->description ?? '',
                    'due_date'                 => $sub->due_date?->toDateString(),
                    'is_completed'             => $sub->is_completed,
                    'completed_at'             => $sub->completed_at?->toIso8601String(),
                    'source'                   => 'guardian_chat',
                    'attachment_path'          => $sub->attachment_path,
                    'attachment_original_name' => $sub->attachment_original_name,
                    'attachment_size'          => $sub->attachment_size,
                ];
            }
        }

        // Also include submission_requests directly linked by student_id (not via chat room)
        $directSubs = SubmissionRequest::where('student_id', $student->id)
            ->where(function ($q) use ($chatRoomIds) {
                $q->whereNull('room_id');
                if ($chatRoomIds->isNotEmpty()) {
                    $q->orWhereNotIn('room_id', $chatRoomIds);
                }
            })
            ->get();
        foreach ($directSubs as $sub) {
            $allSubmissions[] = [
                'id'                       => $sub->id,
                'title'                    => $sub->title,
                'description'              => $sub->description ?? '',
                'due_date'                 => $sub->due_date?->toDateString(),
                'is_completed'             => $sub->is_completed,
                'completed_at'             => $sub->completed_at?->toIso8601String(),
                'source'                   => 'guardian_chat',
                'attachment_path'          => $sub->attachment_path,
                'attachment_original_name' => $sub->attachment_original_name,
                'attachment_size'          => $sub->attachment_size,
            ];
        }

        // 3. 生徒自身が登録した提出物
        $studentSubs = StudentSubmission::where('student_id', $student->id)->get();
        foreach ($studentSubs as $sub) {
            $allSubmissions[] = [
                'id'           => $sub->id,
                'title'        => $sub->title,
                'description'  => $sub->description ?? '',
                'due_date'     => $sub->due_date?->toDateString(),
                'is_completed' => $sub->is_completed,
                'completed_at' => $sub->completed_at?->toIso8601String(),
                'source'       => 'student',
            ];
        }

        // ソート: 未完了を先に、期限順、作成日降順
        usort($allSubmissions, function ($a, $b) {
            if ($a['is_completed'] != $b['is_completed']) {
                return $a['is_completed'] ? 1 : -1;
            }
            return strcmp($a['due_date'] ?? '9999-99-99', $b['due_date'] ?? '9999-99-99');
        });

        return response()->json([
            'success' => true,
            'data'    => $allSubmissions,
        ]);
    }

    /**
     * 生徒自身の提出物を作成/編集
     */
    public function store(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $validated = $request->validate([
            'id'          => 'nullable|integer',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date'    => 'required|date',
        ]);

        if (! empty($validated['id'])) {
            // 編集
            $submission = StudentSubmission::where('id', $validated['id'])
                ->where('student_id', $student->id)
                ->first();

            if (! $submission) {
                return response()->json(['success' => false, 'message' => '提出物が見つかりません。'], 404);
            }

            $submission->update([
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date'    => $validated['due_date'],
            ]);
        } else {
            // 新規作成
            $submission = StudentSubmission::create([
                'student_id'  => $student->id,
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date'    => $validated['due_date'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $submission->fresh(),
            'message' => '保存しました。',
        ]);
    }

    /**
     * 提出物を完了にする
     */
    public function complete(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $validated = $request->validate([
            'id'     => 'required|integer',
            'source' => 'required|string|in:weekly_plan,guardian_chat,student',
        ]);

        $id = $validated['id'];
        $source = $validated['source'];

        if ($source === 'weekly_plan') {
            WeeklyPlanSubmission::whereHas('plan', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })->where('id', $id)->update([
                'is_completed'      => true,
                'completed_at'      => now(),
                'completed_by_type' => 'student',
                'completed_by_id'   => $student->id,
            ]);
        } elseif ($source === 'guardian_chat') {
            // submission_requests linked via chat_rooms or directly by student_id
            SubmissionRequest::where('id', $id)
                ->where(function ($q) use ($student) {
                    $q->where('student_id', $student->id)
                      ->orWhereHas('chatRoom', function ($q2) use ($student) {
                          $q2->where('student_id', $student->id);
                      });
                })
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
        } elseif ($source === 'student') {
            StudentSubmission::where('id', $id)
                ->where('student_id', $student->id)
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
        }

        return response()->json(['success' => true, 'message' => '完了にしました。']);
    }

    /**
     * 提出物を未完了に戻す
     */
    public function uncomplete(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $validated = $request->validate([
            'id'     => 'required|integer',
            'source' => 'required|string|in:weekly_plan,guardian_chat,student',
        ]);

        $id = $validated['id'];
        $source = $validated['source'];

        if ($source === 'weekly_plan') {
            WeeklyPlanSubmission::whereHas('plan', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })->where('id', $id)->update([
                'is_completed'      => false,
                'completed_at'      => null,
                'completed_by_type' => null,
                'completed_by_id'   => null,
            ]);
        } elseif ($source === 'guardian_chat') {
            SubmissionRequest::where('id', $id)
                ->where(function ($q) use ($student) {
                    $q->where('student_id', $student->id)
                      ->orWhereHas('chatRoom', function ($q2) use ($student) {
                          $q2->where('student_id', $student->id);
                      });
                })
                ->update([
                    'is_completed' => false,
                    'completed_at' => null,
                ]);
        } elseif ($source === 'student') {
            StudentSubmission::where('id', $id)
                ->where('student_id', $student->id)
                ->update([
                    'is_completed' => false,
                    'completed_at' => null,
                ]);
        }

        return response()->json(['success' => true, 'message' => '未完了に戻しました。']);
    }

    /**
     * 生徒自身が登録した提出物を削除
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $submission = StudentSubmission::where('id', $id)
            ->where('student_id', $student->id)
            ->first();

        if (! $submission) {
            return response()->json(['success' => false, 'message' => '提出物が見つかりません。'], 404);
        }

        $submission->delete();

        return response()->json(['success' => true, 'message' => '削除しました。']);
    }

    private function getStudent(Request $request): ?Student
    {
        $user = $request->user();

        return Student::where('username', $user->username)->first();
    }
}

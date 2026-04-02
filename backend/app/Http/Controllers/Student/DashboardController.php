<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\SubmissionRequest;
use App\Traits\ResolvesStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ResolvesStudent;

    /**
     * 生徒ダッシュボード情報を返す
     */
    public function index(Request $request): JsonResponse
    {
        // 生徒情報を取得（ユーザーに紐づく生徒レコード）
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => '生徒情報が見つかりません。',
            ], 404);
        }

        // 未読チャットメッセージ数
        $unreadMessages = 0;
        if ($student->studentChatRoom) {
            $unreadMessages = StudentChatMessage::where('room_id', $student->studentChatRoom->id)
                ->where('sender_type', 'staff')
                ->where('is_read', false)
                ->count();
        }

        // 未提出の課題数
        $pendingSubmissions = SubmissionRequest::where('student_id', $student->id)
            ->where('is_completed', false)
            ->where('due_date', '>=', now())
            ->count();

        // 本日のスケジュール
        $dayColumn = 'scheduled_' . strtolower(now()->format('l'));
        $isScheduledToday = $student->{$dayColumn} ?? false;

        // 最近のお便り
        $recentNewsletters = DB::table('newsletters')
            ->where('classroom_id', $student->classroom_id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'published_at']);

        return response()->json([
            'success' => true,
            'data'    => [
                'student'              => $student->only(['id', 'student_name', 'grade_level']),
                'classroom'            => $student->classroom ? $student->classroom->only(['id', 'classroom_name']) : null,
                'unread_messages'      => $unreadMessages,
                'pending_submissions'  => $pendingSubmissions,
                'is_scheduled_today'   => $isScheduledToday,
                'recent_newsletters'   => $recentNewsletters,
            ],
        ]);
    }
}

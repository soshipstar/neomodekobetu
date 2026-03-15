<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\IndividualSupportPlan;
use App\Models\MeetingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 保護者ダッシュボード情報を返す
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 子ども情報
        $children = $user->students()
            ->with('classroom:id,classroom_name')
            ->active()
            ->get(['id', 'student_name', 'classroom_id', 'grade_level']);

        $studentIds = $children->pluck('id');

        // 未読チャットメッセージ数
        $unreadCount = ChatRoom::where('guardian_id', $user->id)
            ->withCount([
                'messages as unread_count' => function ($q) use ($user) {
                    $q->notDeleted()
                      ->where('sender_type', '!=', 'guardian')
                      ->where('is_read', false);
                },
            ])
            ->get()
            ->sum('unread_count');

        // 未確認の支援計画書
        $pendingPlans = IndividualSupportPlan::whereIn('student_id', $studentIds)
            ->where('status', 'submitted')
            ->whereNull('guardian_reviewed_at')
            ->count();

        // 未回答の面談予約
        $pendingMeetings = MeetingRequest::where('guardian_id', $user->id)
            ->where('status', 'pending')
            ->count();

        // 事業所評価アンケート（回答期間中のもの）
        $classroomId = $user->classroom_id;
        $pendingEvaluation = DB::table('facility_evaluation_periods')
            ->where('status', 'collecting')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->whereNotExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                  ->from('facility_guardian_evaluations')
                  ->whereColumn('facility_guardian_evaluations.period_id', 'facility_evaluation_periods.id')
                  ->where('facility_guardian_evaluations.guardian_id', $user->id)
                  ->where('facility_guardian_evaluations.is_submitted', true);
            })
            ->exists();

        // 最新のお便り
        $latestNewsletters = DB::table('newsletters')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'year', 'month', 'published_at']);

        return response()->json([
            'success' => true,
            'data'    => [
                'children'             => $children,
                'unread_messages'      => $unreadCount,
                'pending_plans'        => $pendingPlans,
                'pending_meetings'     => $pendingMeetings,
                'pending_evaluation'   => $pendingEvaluation,
                'latest_newsletters'   => $latestNewsletters,
            ],
        ]);
    }

    /**
     * 保護者に紐づく生徒一覧を取得
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();

        $students = $user->students()
            ->with('classroom:id,classroom_name')
            ->get(['id', 'student_name', 'classroom_id', 'grade_level', 'birth_date', 'status']);

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }
}

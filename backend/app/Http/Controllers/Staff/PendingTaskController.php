<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\IndividualSupportPlan;
use App\Models\MeetingRequest;
use App\Models\MonitoringRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingTaskController extends Controller
{
    /**
     * 未対応タスク一覧を取得
     * 振替未処理、面談未回答、計画書未完成、モニタリング未完成などを集約
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $tasks = [];

        // 1. 振替依頼（未対応）
        $pendingMakeups = AbsenceNotification::with('student:id,student_name')
            ->whereHas('student', function ($q) use ($classroomId) {
                if ($classroomId) {
                    $q->where('classroom_id', $classroomId);
                }
            })
            ->where('makeup_status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        foreach ($pendingMakeups as $makeup) {
            $tasks[] = [
                'type'       => 'makeup_request',
                'label'      => '振替依頼',
                'detail'     => $makeup->student->student_name . 'さんの振替依頼が未処理です。',
                'student'    => $makeup->student->student_name,
                'id'         => $makeup->id,
                'created_at' => $makeup->created_at,
            ];
        }

        // 2. 面談予約（保護者が逆提案した未対応のもの）
        $counterMeetings = MeetingRequest::with('student:id,student_name')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->where('status', 'guardian_counter')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($counterMeetings as $meeting) {
            $tasks[] = [
                'type'       => 'meeting_counter',
                'label'      => '面談日程調整',
                'detail'     => $meeting->student->student_name . 'さんの保護者から別日程の提案があります。',
                'student'    => $meeting->student->student_name,
                'id'         => $meeting->id,
                'created_at' => $meeting->updated_at,
            ];
        }

        // 3. 下書きの支援計画書
        $draftPlans = IndividualSupportPlan::with('student:id,student_name')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->where('status', 'draft')
            ->where('created_by', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        foreach ($draftPlans as $plan) {
            $tasks[] = [
                'type'       => 'draft_plan',
                'label'      => '支援計画書（下書き）',
                'detail'     => $plan->student->student_name . 'さんの支援計画書が下書き状態です。',
                'student'    => $plan->student->student_name,
                'id'         => $plan->id,
                'created_at' => $plan->updated_at,
            ];
        }

        // 日付順でソート
        usort($tasks, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return response()->json([
            'success' => true,
            'data'    => $tasks,
            'count'   => count($tasks),
        ]);
    }

    /**
     * タスクを完了にする
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type');

        switch ($type) {
            case 'makeup_request':
                $item = AbsenceNotification::find($id);
                if ($item) {
                    $item->update(['makeup_status' => 'completed']);
                }
                break;

            case 'meeting_counter':
                $item = MeetingRequest::find($id);
                if ($item) {
                    $item->update(['status' => 'confirmed']);
                }
                break;

            case 'draft_plan':
                $item = IndividualSupportPlan::find($id);
                if ($item) {
                    $item->update(['status' => 'submitted']);
                }
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => '不明なタスクタイプです。',
                ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'タスクを完了しました。',
        ]);
    }
}

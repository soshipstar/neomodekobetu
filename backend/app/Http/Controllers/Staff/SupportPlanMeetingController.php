<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\SupportPlanMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 個別支援会議 議事録の CRUD。各個別支援計画(原案)に紐づく。
 * 原案を保護者に提示する前に行う会議の記録 (会議日・出席者・協議内容)。
 */
class SupportPlanMeetingController extends Controller
{
    /** 計画の所属教室をスタッフが操作できるか確認 */
    private function authorize(Request $request, IndividualSupportPlan $plan): void
    {
        $plan->loadMissing('student');
        $user = $request->user();
        if ($plan->student
            && $user->classroom_id
            && ! in_array($plan->student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'アクセス権限がありません。');
        }
    }

    /** 計画に紐づく議事録一覧 */
    public function index(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);

        $meetings = $plan->meetings()
            ->with('creator:id,full_name')
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $meetings]);
    }

    /** 議事録を追加 */
    public function store(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);

        $validated = $request->validate([
            'meeting_date' => 'nullable|date',
            'attendees'    => 'nullable|string|max:2000',
            'discussion'   => 'nullable|string|max:20000',
        ]);

        $meeting = SupportPlanMeeting::create([
            'plan_id'      => $plan->id,
            'meeting_date' => $validated['meeting_date'] ?? null,
            'attendees'    => $validated['attendees'] ?? null,
            'discussion'   => $validated['discussion'] ?? null,
            'created_by'   => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $meeting->load('creator:id,full_name'),
            'message' => '議事録を保存しました。',
        ], 201);
    }

    /** 議事録を更新 */
    public function update(Request $request, IndividualSupportPlan $plan, SupportPlanMeeting $meeting): JsonResponse
    {
        $this->authorize($request, $plan);
        if ($meeting->plan_id !== $plan->id) {
            abort(404);
        }

        $validated = $request->validate([
            'meeting_date' => 'nullable|date',
            'attendees'    => 'nullable|string|max:2000',
            'discussion'   => 'nullable|string|max:20000',
        ]);

        $meeting->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $meeting->fresh('creator:id,full_name'),
            'message' => '議事録を更新しました。',
        ]);
    }

    /** 議事録を削除 */
    public function destroy(Request $request, IndividualSupportPlan $plan, SupportPlanMeeting $meeting): JsonResponse
    {
        $this->authorize($request, $plan);
        if ($meeting->plan_id !== $plan->id) {
            abort(404);
        }

        $meeting->delete();

        return response()->json(['success' => true, 'message' => '議事録を削除しました。']);
    }
}

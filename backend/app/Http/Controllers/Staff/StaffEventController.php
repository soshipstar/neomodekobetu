<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffEventController extends Controller
{
    /**
     * イベント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Event::with('classroom:id,classroom_name');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('event_date', $request->month)
                  ->whereYear('event_date', $request->year);
        }

        $events = $query->orderBy('event_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $events,
        ]);
    }

    /**
     * イベントを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_date'        => 'required|date',
            'event_name'        => 'required|string|max:255',
            'event_description' => 'nullable|string|max:2000',
            'target_audience'   => 'nullable|string|max:100',
            'event_color'       => 'nullable|string|max:20',
            'staff_comment'     => 'nullable|string|max:1000',
            'guardian_message'  => 'nullable|string|max:1000',
        ]);

        $event = Event::create(array_merge($validated, [
            'classroom_id' => $user->classroom_id,
            'created_by'   => $user->id,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $event,
            'message' => 'イベントを登録しました。',
        ], 201);
    }

    /**
     * イベントを更新
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $event->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'event_date'        => 'sometimes|date',
            'event_name'        => 'sometimes|string|max:255',
            'event_description' => 'nullable|string|max:2000',
            'target_audience'   => 'nullable|string|max:100',
            'event_color'       => 'nullable|string|max:20',
            'staff_comment'     => 'nullable|string|max:1000',
            'guardian_message'  => 'nullable|string|max:1000',
        ]);

        $event->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $event->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * イベントを削除
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $event->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * イベントの参加登録一覧を取得
     */
    public function registrations(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $event->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $registrations = EventRegistration::where('event_id', $event->id)
            ->with([
                'student:id,student_name',
                'guardian:id,full_name',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $registrations,
        ]);
    }
}

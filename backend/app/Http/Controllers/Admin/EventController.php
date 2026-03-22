<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * イベント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('classroom:id,classroom_name');

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        // month パラメータは 'YYYY-MM' 形式を受け付ける
        if ($request->filled('month')) {
            $parts = explode('-', $request->month);
            if (count($parts) === 2) {
                $query->whereYear('event_date', $parts[0])
                      ->whereMonth('event_date', $parts[1]);
            }
        } elseif ($request->filled('year')) {
            $query->whereYear('event_date', $request->year);
            if ($request->filled('month_num')) {
                $query->whereMonth('event_date', $request->month_num);
            }
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
        $validated = $request->validate([
            'classroom_id'      => 'required|exists:classrooms,id',
            'event_date'        => 'required|date',
            'event_name'        => 'required|string|max:255',
            'event_description' => 'nullable|string|max:2000',
            'target_audience'   => 'nullable|string|max:100',
            'event_color'       => 'nullable|string|max:20',
            'staff_comment'     => 'nullable|string|max:1000',
            'guardian_message'  => 'nullable|string|max:1000',
            'max_capacity'     => 'nullable|integer|min:1',
        ]);

        $event = Event::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $event,
            'message' => 'イベントを登録しました。',
        ], 201);
    }

    /**
     * イベント詳細を取得
     */
    public function show(Event $event): JsonResponse
    {
        $event->load(['classroom:id,classroom_name', 'registrations']);

        return response()->json([
            'success' => true,
            'data'    => $event,
        ]);
    }

    /**
     * イベントを更新
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id'      => 'sometimes|exists:classrooms,id',
            'event_date'        => 'sometimes|date',
            'event_name'        => 'sometimes|string|max:255',
            'event_description' => 'nullable|string|max:2000',
            'target_audience'   => 'nullable|string|max:100',
            'event_color'       => 'nullable|string|max:20',
            'staff_comment'     => 'nullable|string|max:1000',
            'guardian_message'  => 'nullable|string|max:1000',
            'max_capacity'     => 'nullable|integer|min:1',
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
    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianEventController extends Controller
{
    /**
     * 保護者向けイベント一覧（今後のイベントのみ）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 保護者の子供の教室IDを取得
        $classroomIds = \App\Models\Student::whereHas('chatRooms', function ($q) use ($user) {
            $q->where('guardian_id', $user->id);
        })->pluck('classroom_id')->unique()->filter()->values();

        $query = Event::whereIn('classroom_id', $classroomIds);

        // upcoming=true の場合、今日以降のイベントのみ
        if ($request->boolean('upcoming', false)) {
            $query->where('event_date', '>=', now()->startOfDay());
        }

        $events = $query->orderBy('event_date')->get();

        $data = $events->map(fn ($e) => [
            'id'          => $e->id,
            'event_name'  => $e->event_name,
            'event_date'  => $e->event_date?->format('Y-m-d'),
            'description' => $e->event_description,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}

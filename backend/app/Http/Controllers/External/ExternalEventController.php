<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalEventController extends Controller
{
    /**
     * 外部サイト向けイベント一覧API
     *
     * GET /api/external/events?classroom_id=1&month=2026-04
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'month'        => 'nullable|regex:/^\d{4}-\d{2}$/',
        ]);

        $query = Event::where('classroom_id', $request->classroom_id)
            ->select([
                'id',
                'event_date',
                'event_name',
                'event_description',
                'target_audience',
                'event_color',
                'guardian_message',
                'max_capacity',
            ]);

        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->month);
            $query->whereYear('event_date', $year)
                  ->whereMonth('event_date', $month);
        } else {
            // デフォルト: 今日以降のイベント
            $query->where('event_date', '>=', now()->toDateString());
        }

        $events = $query->orderBy('event_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $events,
        ]);
    }
}

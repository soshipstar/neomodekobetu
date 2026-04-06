<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalSupportPlanController extends Controller
{
    /**
     * 外部サイト向け支援案一覧API
     *
     * GET /api/external/support-plans?classroom_id=2&date=2026-04-06
     * GET /api/external/support-plans?classroom_id=2&day_of_week=月
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'date'         => 'nullable|date',
            'day_of_week'  => 'nullable|string',
        ]);

        $query = ActivitySupportPlan::where('classroom_id', $request->classroom_id)
            ->select([
                'id',
                'activity_name',
                'activity_date',
                'plan_type',
                'target_grade',
                'activity_purpose',
                'activity_content',
                'tags',
                'day_of_week',
                'five_domains_consideration',
                'activity_schedule',
                'total_duration',
            ]);

        if ($request->filled('date')) {
            $date = $request->date;
            $query->where('activity_date', $date);
        }

        if ($request->filled('day_of_week')) {
            $day = $request->day_of_week;
            $query->where(function ($q) use ($day) {
                $q->where('day_of_week', $day)
                  ->orWhere('day_of_week', 'LIKE', "$day,%")
                  ->orWhere('day_of_week', 'LIKE', "%,$day,%")
                  ->orWhere('day_of_week', 'LIKE', "%,$day");
            });
        }

        $plans = $query->orderByDesc('activity_date')->limit(20)->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Holiday;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentScheduleController extends Controller
{
    /**
     * 生徒のスケジュール（イベント・休日含む）を取得
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $classroomId = $student->classroom_id;

        // 月指定があればその月、なければ今月
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // イベント
        $events = Event::where('classroom_id', $classroomId)
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->orderBy('event_date')
            ->get(['id', 'event_date', 'event_name', 'event_description', 'event_color']);

        // 休日
        $holidays = Holiday::where('classroom_id', $classroomId)
            ->whereYear('holiday_date', $year)
            ->whereMonth('holiday_date', $month)
            ->orderBy('holiday_date')
            ->get(['id', 'holiday_date', 'holiday_name']);

        // 生徒の通所予定曜日
        $scheduledDays = $student->getScheduledDays();

        return response()->json([
            'success' => true,
            'data'    => [
                'events'         => $events,
                'holidays'       => $holidays,
                'scheduled_days' => $scheduledDays,
                'year'           => (int) $year,
                'month'          => (int) $month,
            ],
        ]);
    }

    /**
     * リクエストから生徒情報を取得
     */
    private function getStudent(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Student) {
            return $user;
        }

        return Student::where('username', $user->username)->first();
    }
}

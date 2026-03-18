<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\AdditionalUsage;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Holiday;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdditionalUsageController extends Controller
{
    /**
     * 追加利用一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = AdditionalUsage::with('student:id,student_name,classroom_id');

        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('usage_date', $request->month)
                  ->whereYear('usage_date', $request->year);
        }

        $usages = $query->orderBy('usage_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $usages,
        ]);
    }

    /**
     * 追加利用を登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'usage_date' => 'required|date',
            'notes'      => 'nullable|string|max:500',
        ]);

        // 教室アクセス権チェック
        if ($user->classroom_id) {
            $student = Student::where('id', $validated['student_id'])
                ->where('classroom_id', $user->classroom_id)
                ->first();

            if (! $student) {
                return response()->json(['success' => false, 'message' => '生徒が見つかりません。'], 404);
            }
        }

        // 重複チェック
        $existing = AdditionalUsage::where('student_id', $validated['student_id'])
            ->where('usage_date', $validated['usage_date'])
            ->first();

        if ($existing) {
            $existing->update(['notes' => $validated['notes'] ?? null]);

            return response()->json([
                'success' => true,
                'data'    => $existing->fresh(),
                'message' => '更新しました。',
            ]);
        }

        $usage = AdditionalUsage::create([
            'student_id' => $validated['student_id'],
            'usage_date' => $validated['usage_date'],
            'notes'      => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $usage,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * 一括変更（旧additional_usage_api.php互換）
     * actions: add(追加利用), remove(追加利用削除), cancel(通常日キャンセル), restore(キャンセル取消)
     */
    public function batchUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'changes'    => 'required|array',
            'changes.*.date'   => 'required|date',
            'changes.*.action' => 'required|in:add,remove,cancel,restore',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $cancelledDates = [];

        DB::transaction(function () use ($validated, $user, $student, &$cancelledDates) {
            foreach ($validated['changes'] as $change) {
                $date = $change['date'];
                $studentId = $validated['student_id'];

                switch ($change['action']) {
                    case 'add':
                        AdditionalUsage::updateOrCreate(
                            ['student_id' => $studentId, 'usage_date' => $date],
                            ['created_by' => $user->id]
                        );
                        break;

                    case 'remove':
                        AdditionalUsage::where('student_id', $studentId)
                            ->where('usage_date', $date)
                            ->delete();
                        break;

                    case 'cancel':
                        AbsenceNotification::updateOrCreate(
                            ['student_id' => $studentId, 'absence_date' => $date],
                            ['reason' => 'スタッフによるキャンセル', 'makeup_status' => 'none']
                        );
                        $cancelledDates[] = $date;
                        break;

                    case 'restore':
                        AbsenceNotification::where('student_id', $studentId)
                            ->where('absence_date', $date)
                            ->delete();
                        break;
                }
            }

            // Send chat notifications for cancelled dates
            if (! empty($cancelledDates)) {
                $room = ChatRoom::where('student_id', $student->id)->first();

                if ($room) {
                    $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];

                    foreach ($cancelledDates as $date) {
                        $dateObj = Carbon::parse($date);
                        $dateStr = $dateObj->format('n月j日');
                        $dayOfWeek = $dayOfWeekMap[(int) $dateObj->format('w')];

                        ChatMessage::create([
                            'room_id'     => $room->id,
                            'sender_id'   => $user->id,
                            'sender_type' => 'staff',
                            'message'     => "【利用日変更】{$student->student_name}さんの{$dateStr}({$dayOfWeek})の利用がキャンセルされました。",
                        ]);
                    }

                    $room->update(['last_message_at' => now()]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => '保存しました。',
        ]);
    }

    /**
     * 生徒の月間利用状況を取得（カレンダー表示用）
     */
    public function studentMonth(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'year'       => 'required|integer',
            'month'      => 'required|integer|min:1|max:12',
        ]);

        $student = Student::findOrFail($request->student_id);
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // 追加利用日
        $additionalDates = AdditionalUsage::where('student_id', $student->id)
            ->whereYear('usage_date', $request->year)
            ->whereMonth('usage_date', $request->month)
            ->pluck('usage_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        // キャンセル日
        $cancelledDates = AbsenceNotification::where('student_id', $student->id)
            ->whereYear('absence_date', $request->year)
            ->whereMonth('absence_date', $request->month)
            ->pluck('absence_date')
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // 通常スケジュール
        $schedule = [
            'sunday'    => (bool) $student->scheduled_sunday,
            'monday'    => (bool) $student->scheduled_monday,
            'tuesday'   => (bool) $student->scheduled_tuesday,
            'wednesday' => (bool) $student->scheduled_wednesday,
            'thursday'  => (bool) $student->scheduled_thursday,
            'friday'    => (bool) $student->scheduled_friday,
            'saturday'  => (bool) $student->scheduled_saturday,
        ];

        // 休日
        $classroomId = $student->classroom_id ?? $user->classroom_id;
        $holidayDates = [];
        if ($classroomId) {
            $holidayDates = Holiday::where('classroom_id', $classroomId)
                ->whereYear('holiday_date', $request->year)
                ->whereMonth('holiday_date', $request->month)
                ->pluck('holiday_date')
                ->map(fn ($d) => $d->format('Y-m-d'))
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'student_name'     => $student->student_name,
                'schedule'         => $schedule,
                'additional_dates' => $additionalDates,
                'cancelled_dates'  => $cancelledDates,
                'holiday_dates'    => $holidayDates,
            ],
        ]);
    }

    /**
     * 追加利用を削除
     */
    public function destroy(Request $request, AdditionalUsage $usage): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id) {
            $student = $usage->student;
            if ($student && $student->classroom_id !== $user->classroom_id) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $usage->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}

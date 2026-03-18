<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffHolidayController extends Controller
{
    /**
     * 休日一覧を取得（検索機能付き）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Holiday::query()->leftJoin('users', 'holidays.created_by', '=', 'users.id')
            ->select('holidays.*', 'users.full_name as created_by_name');

        if ($classroomId) {
            $query->where('holidays.classroom_id', $classroomId);
        }

        if ($request->filled('year')) {
            $query->whereYear('holidays.holiday_date', $request->year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('holidays.holiday_date', $request->month);
        }

        // キーワード検索
        if ($request->filled('keyword')) {
            $query->where('holidays.holiday_name', 'like', '%' . $request->keyword . '%');
        }

        // 期間検索
        if ($request->filled('start_date')) {
            $query->where('holidays.holiday_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('holidays.holiday_date', '<=', $request->end_date);
        }

        $holidays = $query->orderBy('holidays.holiday_date', 'desc')->get();

        $mapped = $holidays->map(function ($holiday) {
            return [
                'id'              => $holiday->id,
                'date'            => $holiday->holiday_date->format('Y-m-d'),
                'name'            => $holiday->holiday_name,
                'holiday_type'    => $holiday->holiday_type,
                'is_recurring'    => $holiday->holiday_type === 'regular',
                'created_by_name' => $holiday->created_by_name ?? null,
                'created_at'      => $holiday->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $mapped,
        ]);
    }

    /**
     * 休日を登録
     * - regular: 年度内の該当曜日すべてに一括登録
     * - special: 指定日のみ登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'holiday_date' => 'required_without:date|date',
            'date'         => 'required_without:holiday_date|date',
            'holiday_name' => 'required_without:name|string|max:100',
            'name'         => 'required_without:holiday_name|string|max:100',
            'holiday_type' => 'nullable|string|in:regular,special',
            'is_recurring' => 'nullable|boolean',
        ]);

        $holidayDate = $validated['holiday_date'] ?? $validated['date'];
        $holidayName = $validated['holiday_name'] ?? $validated['name'];
        $holidayType = $validated['holiday_type']
            ?? (isset($validated['is_recurring']) && $validated['is_recurring'] ? 'regular' : 'special');

        $classroomId = $user->classroom_id;

        if ($holidayType === 'regular') {
            // 定期休日：年度内の該当曜日すべてに登録
            $baseDate = Carbon::parse($holidayDate);
            $baseDayOfWeek = $baseDate->dayOfWeek; // 0=Sunday

            // 年度計算（4月始まり）
            if ($baseDate->month >= 4) {
                $fiscalStart = Carbon::create($baseDate->year, 4, 1);
                $fiscalEnd = Carbon::create($baseDate->year + 1, 3, 31);
            } else {
                $fiscalStart = Carbon::create($baseDate->year - 1, 4, 1);
                $fiscalEnd = Carbon::create($baseDate->year, 3, 31);
            }

            $insertedCount = 0;
            $skippedCount = 0;

            $current = $fiscalStart->copy();
            while ($current->lte($fiscalEnd)) {
                if ($current->dayOfWeek === $baseDayOfWeek) {
                    // 重複チェック
                    $exists = Holiday::where('holiday_date', $current->format('Y-m-d'))
                        ->where(function ($q) use ($classroomId) {
                            if ($classroomId) {
                                $q->where('classroom_id', $classroomId);
                            }
                        })
                        ->exists();

                    if (!$exists) {
                        Holiday::create([
                            'classroom_id' => $classroomId,
                            'holiday_date' => $current->format('Y-m-d'),
                            'holiday_name' => $holidayName,
                            'holiday_type' => $holidayType,
                            'created_by'   => $user->id,
                        ]);
                        $insertedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
                $current->addDay();
            }

            if ($insertedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '該当曜日はすべて既に登録済みです。',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "定期休日として{$insertedCount}件の休日を登録しました。",
                'count'   => $insertedCount,
                'skipped' => $skippedCount,
            ], 201);
        }

        // 特別休日：指定日のみ登録
        // 重複チェック
        $exists = Holiday::where('holiday_date', $holidayDate)
            ->where(function ($q) use ($classroomId) {
                if ($classroomId) {
                    $q->where('classroom_id', $classroomId);
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'この日付は既に休日として登録されています。',
            ], 422);
        }

        $holiday = Holiday::create([
            'classroom_id' => $classroomId,
            'holiday_date' => $holidayDate,
            'holiday_name' => $holidayName,
            'holiday_type' => $holidayType,
            'created_by'   => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $holiday,
            'message' => '休日を登録しました。',
        ], 201);
    }

    /**
     * 休日を削除
     */
    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $holiday->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}

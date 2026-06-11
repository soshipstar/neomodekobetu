<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * 休日一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Holiday::query();

        if ($user->is_master) {
            if ($request->filled('classroom_id')) {
                $query->where('classroom_id', $request->classroom_id);
            }
        } else {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('year')) {
            $query->whereYear('holiday_date', $request->year);
        }

        $holidays = $query->orderBy('holiday_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $holidays,
        ]);
    }

    /**
     * 休日を登録
     *
     * 認可:
     *  - 非マスター: classroom_id をリクエスト値より優先せず、必ず自身の現在教室で登録する
     *    (フロントが classroom_id を送らない/誤って他教室を送るケースを安全側に倒す)
     *  - マスター: classroom_id 必須。switchableClassroomIds() に含まれる教室のみ許可
     *    (cross-company の意図しない登録を防ぐ)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'holiday_date' => 'required|date',
            'holiday_name' => 'required|string|max:100',
        ]);

        if ($user->is_master) {
            $classroomId = $validated['classroom_id'] ?? $user->classroom_id;
            if (!$classroomId) {
                return response()->json([
                    'success' => false,
                    'message' => '教室を指定してください。',
                ], 422);
            }
            if (!in_array((int) $classroomId, $user->switchableClassroomIds(), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'この教室へのアクセス権限がありません。',
                ], 403);
            }
        } else {
            // 非マスターはリクエスト値を信用せず、自分の現在教室で固定する
            $classroomId = $user->classroom_id;
            if (!$classroomId) {
                return response()->json([
                    'success' => false,
                    'message' => '所属教室が未設定のため休日を登録できません。',
                ], 422);
            }
        }

        $holiday = Holiday::create([
            'classroom_id' => $classroomId,
            'holiday_date' => $validated['holiday_date'],
            'holiday_name' => $validated['holiday_name'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $holiday,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * 休日を削除
     */
    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        // ARCH-AUTH 統一: classroom_id 完全一致比較 (複数所属で誤拒否) を統一基盤へ。
        $this->authorizeClassroomId($request->user(), $holiday->classroom_id, 'アクセス権限がありません。');

        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}

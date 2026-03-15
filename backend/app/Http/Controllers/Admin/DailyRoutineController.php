<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyRoutine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyRoutineController extends Controller
{
    /**
     * デイリールーティン一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = DailyRoutine::query();

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $routines = $query->orderBy('classroom_id')->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $routines,
        ]);
    }

    /**
     * デイリールーティンを作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id'    => 'required|exists:classrooms,id',
            'routine_name'    => 'required|string|max:100',
            'routine_content' => 'nullable|string|max:500',
            'scheduled_time'  => 'nullable|string|max:20',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
        ]);

        $routine = DailyRoutine::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $routine,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * デイリールーティン詳細を取得
     */
    public function show(DailyRoutine $dailyRoutine): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $dailyRoutine,
        ]);
    }

    /**
     * デイリールーティンを更新
     */
    public function update(Request $request, DailyRoutine $dailyRoutine): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id'    => 'sometimes|exists:classrooms,id',
            'routine_name'    => 'sometimes|string|max:100',
            'routine_content' => 'nullable|string|max:500',
            'scheduled_time'  => 'nullable|string|max:20',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
        ]);

        $dailyRoutine->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $dailyRoutine->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * デイリールーティンを削除
     */
    public function destroy(DailyRoutine $dailyRoutine): JsonResponse
    {
        $dailyRoutine->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}

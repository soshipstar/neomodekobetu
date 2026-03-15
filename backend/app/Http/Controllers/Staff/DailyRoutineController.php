<?php

namespace App\Http\Controllers\Staff;

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
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = DailyRoutine::query();

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $routines = $query->orderBy('sort_order')->get();

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
        $user = $request->user();

        $validated = $request->validate([
            'routine_name'    => 'required|string|max:100',
            'routine_content' => 'nullable|string|max:500',
            'scheduled_time'  => 'nullable|string|max:20',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
        ]);

        $routine = DailyRoutine::create([
            'classroom_id'    => $user->classroom_id,
            'routine_name'    => $validated['routine_name'],
            'routine_content' => $validated['routine_content'] ?? null,
            'scheduled_time'  => $validated['scheduled_time'] ?? null,
            'sort_order'      => $validated['sort_order'] ?? 0,
            'is_active'       => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $routine,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * デイリールーティンを更新
     */
    public function update(Request $request, DailyRoutine $routine): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $routine->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'routine_name'    => 'sometimes|string|max:100',
            'routine_content' => 'nullable|string|max:500',
            'scheduled_time'  => 'nullable|string|max:20',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
        ]);

        $routine->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $routine->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * デイリールーティンを削除
     */
    public function destroy(Request $request, DailyRoutine $routine): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $routine->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $routine->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * デイリールーティンの並び替え
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:daily_routines,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            DailyRoutine::where('id', $id)
                ->when($user->classroom_id, fn ($q) => $q->where('classroom_id', $user->classroom_id))
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => '並び替えを保存しました。',
        ]);
    }
}

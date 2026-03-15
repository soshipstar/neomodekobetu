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

        $mapped = $routines->map(function ($routine) {
            $data = $routine->toArray();
            $data['name'] = $routine->routine_name;
            $data['description'] = $routine->routine_content;
            $data['duration'] = $routine->scheduled_time ? (int) $routine->scheduled_time : null;
            return $data;
        });

        return response()->json([
            'success' => true,
            'data'    => $mapped,
        ]);
    }

    /**
     * デイリールーティンを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'routine_name'    => 'required_without:name|string|max:100',
            'name'            => 'required_without:routine_name|string|max:100',
            'routine_content' => 'nullable|string|max:500',
            'description'     => 'nullable|string|max:500',
            'scheduled_time'  => 'nullable|string|max:20',
            'duration'        => 'nullable|integer',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
        ]);

        $routineName = $validated['routine_name'] ?? $validated['name'];
        $routineContent = $validated['routine_content'] ?? $validated['description'] ?? null;
        $scheduledTime = $validated['scheduled_time'] ?? (isset($validated['duration']) ? (string) $validated['duration'] : null);

        $routine = DailyRoutine::create([
            'classroom_id'    => $user->classroom_id,
            'routine_name'    => $routineName,
            'routine_content' => $routineContent,
            'scheduled_time'  => $scheduledTime,
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
            'name'            => 'sometimes|string|max:100',
            'routine_content' => 'nullable|string|max:500',
            'description'     => 'nullable|string|max:500',
            'scheduled_time'  => 'nullable|string|max:20',
            'duration'        => 'nullable|integer',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
        ]);

        $updateData = [];
        if (isset($validated['routine_name']) || isset($validated['name'])) {
            $updateData['routine_name'] = $validated['routine_name'] ?? $validated['name'];
        }
        if (array_key_exists('routine_content', $validated) || array_key_exists('description', $validated)) {
            $updateData['routine_content'] = $validated['routine_content'] ?? $validated['description'] ?? null;
        }
        if (array_key_exists('scheduled_time', $validated) || array_key_exists('duration', $validated)) {
            $updateData['scheduled_time'] = $validated['scheduled_time'] ?? (isset($validated['duration']) ? (string) $validated['duration'] : null);
        }
        if (array_key_exists('sort_order', $validated)) {
            $updateData['sort_order'] = $validated['sort_order'];
        }
        if (array_key_exists('is_active', $validated)) {
            $updateData['is_active'] = $validated['is_active'];
        }

        $routine->update($updateData);

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

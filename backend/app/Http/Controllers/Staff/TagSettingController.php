<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassroomTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagSettingController extends Controller
{
    /**
     * タグ設定一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = ClassroomTag::query();

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $tags = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $tags,
        ]);
    }

    /**
     * タグを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'tag_name'   => 'required|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $tag = ClassroomTag::create([
            'classroom_id' => $user->classroom_id,
            'tag_name'     => $validated['tag_name'],
            'sort_order'   => $validated['sort_order'] ?? 0,
            'is_active'    => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $tag,
            'message' => 'タグを作成しました。',
        ], 201);
    }

    /**
     * タグを更新
     */
    public function update(Request $request, ClassroomTag $tag): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $tag->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'tag_name'   => 'sometimes|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $tag->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $tag->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * タグを削除
     */
    public function destroy(Request $request, ClassroomTag $tag): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $tag->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * タグの並び替え
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:classroom_tags,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            ClassroomTag::where('id', $id)
                ->when($user->classroom_id, fn ($q) => $q->where('classroom_id', $user->classroom_id))
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => '並び替えを保存しました。',
        ]);
    }
}

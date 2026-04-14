<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassroomTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        $tags = $query->orderBy('sort_order')->get();

        $mapped = $tags->map(function ($tag) {
            $data = $tag->toArray();
            $data['name'] = $tag->tag_name;
            return $data;
        });

        return response()->json([
            'success' => true,
            'data'    => $mapped,
        ]);
    }

    /**
     * タグを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'tag_name'   => 'required_without:name|string|max:50',
            'name'       => 'required_without:tag_name|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $tagName = $validated['tag_name'] ?? $validated['name'];

        $tag = ClassroomTag::create([
            'classroom_id' => $user->classroom_id,
            'tag_name'     => $tagName,
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

        if ($user->classroom_id && !in_array($tag->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'tag_name'   => 'sometimes|string|max:50',
            'name'       => 'sometimes|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $updateData = [];
        if (isset($validated['tag_name']) || isset($validated['name'])) {
            $updateData['tag_name'] = $validated['tag_name'] ?? $validated['name'];
        }
        if (array_key_exists('sort_order', $validated)) {
            $updateData['sort_order'] = $validated['sort_order'];
        }
        if (array_key_exists('is_active', $validated)) {
            $updateData['is_active'] = $validated['is_active'];
        }

        $tag->update($updateData);

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

        if ($user->classroom_id && !in_array($tag->classroom_id, $user->switchableClassroomIds(), true)) {
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
                ->when($user->classroom_id, fn ($q) => $q->whereIn('classroom_id', $user->accessibleClassroomIds()))
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => '並び替えを保存しました。',
        ]);
    }

    /**
     * タグを一括保存（レガシー互換: 全削除→再挿入）
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        if (!$classroomId) {
            return response()->json(['success' => false, 'message' => '教室が設定されていません。'], 400);
        }

        $validated = $request->validate([
            'tags'   => 'required|array',
            'tags.*' => 'string|max:50',
        ]);

        DB::transaction(function () use ($classroomId, $validated) {
            ClassroomTag::where('classroom_id', $classroomId)->delete();

            $sortOrder = 1;
            foreach ($validated['tags'] as $tagName) {
                $tagName = trim($tagName);
                if ($tagName !== '') {
                    ClassroomTag::create([
                        'classroom_id' => $classroomId,
                        'tag_name'     => $tagName,
                        'sort_order'   => $sortOrder,
                        'is_active'    => true,
                    ]);
                    $sortOrder++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'タグ設定を保存しました。',
        ]);
    }

    /**
     * デフォルトタグにリセット
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        if (!$classroomId) {
            return response()->json(['success' => false, 'message' => '教室が設定されていません。'], 400);
        }

        $defaultTags = ['動画', '食', '学習', 'イベント', 'その他'];

        DB::transaction(function () use ($classroomId, $defaultTags) {
            ClassroomTag::where('classroom_id', $classroomId)->delete();

            $sortOrder = 1;
            foreach ($defaultTags as $tagName) {
                ClassroomTag::create([
                    'classroom_id' => $classroomId,
                    'tag_name'     => $tagName,
                    'sort_order'   => $sortOrder,
                    'is_active'    => true,
                ]);
                $sortOrder++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'デフォルトタグにリセットしました。',
        ]);
    }
}

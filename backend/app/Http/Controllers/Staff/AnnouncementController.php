<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * お知らせ一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Announcement::with(['creator:id,full_name'])
            ->withCount('reads');

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        $announcements = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        // ターゲット生徒情報を付与
        $announcements->getCollection()->transform(function ($announcement) {
            if ($announcement->target_type === 'selected') {
                $announcement->load('targetStudents:id,student_name');
            }
            return $announcement;
        });

        return response()->json([
            'success' => true,
            'data'    => $announcements,
        ]);
    }

    /**
     * お知らせを新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'priority'          => 'sometimes|string|in:normal,important,urgent',
            'target_type'       => 'sometimes|string|in:all,selected',
            'target_student_ids' => 'array',
            'target_student_ids.*' => 'integer|exists:students,id',
        ]);

        $announcement = Announcement::create([
            'classroom_id' => $request->user()->classroom_id,
            'title'        => $validated['title'],
            'content'      => $validated['content'],
            'priority'     => $validated['priority'] ?? 'normal',
            'target_type'  => $validated['target_type'] ?? 'all',
            'is_published' => false,
            'created_by'   => $request->user()->id,
        ]);

        // 個別配信の場合、ターゲット生徒を登録
        if (($validated['target_type'] ?? 'all') === 'selected' && !empty($validated['target_student_ids'])) {
            $announcement->targetStudents()->sync($validated['target_student_ids']);
        }

        $announcement->load(['creator:id,full_name', 'targetStudents:id,student_name']);
        $announcement->loadCount('reads');

        return response()->json([
            'success' => true,
            'data'    => $announcement,
            'message' => 'お知らせを作成しました。',
        ], 201);
    }

    /**
     * お知らせを更新
     */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'content'           => 'sometimes|required|string',
            'priority'          => 'sometimes|string|in:normal,important,urgent',
            'target_type'       => 'sometimes|string|in:all,selected',
            'target_student_ids' => 'array',
            'target_student_ids.*' => 'integer|exists:students,id',
        ]);

        $announcement->update(collect($validated)->except('target_student_ids')->toArray());

        // ターゲット生徒更新
        if (isset($validated['target_type'])) {
            if ($validated['target_type'] === 'selected' && !empty($validated['target_student_ids'])) {
                $announcement->targetStudents()->sync($validated['target_student_ids']);
            } elseif ($validated['target_type'] === 'all') {
                $announcement->targetStudents()->detach();
            }
        }

        $announcement->load(['creator:id,full_name', 'targetStudents:id,student_name']);
        $announcement->loadCount('reads');

        return response()->json([
            'success' => true,
            'data'    => $announcement,
            'message' => 'お知らせを更新しました。',
        ]);
    }

    /**
     * お知らせを削除
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'お知らせを削除しました。',
        ]);
    }

    /**
     * お知らせを公開
     */
    public function publish(Announcement $announcement): JsonResponse
    {
        $announcement->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $announcement->fresh(),
            'message' => 'お知らせを公開しました。',
        ]);
    }

    /**
     * お知らせを非公開にする
     */
    public function unpublish(Announcement $announcement): JsonResponse
    {
        $announcement->update([
            'is_published' => false,
            'published_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $announcement->fresh(),
            'message' => 'お知らせを非公開にしました。',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomSwitchController extends Controller
{
    /**
     * 現在のユーザーが所属する教室一覧を取得
     *
     * - マスター管理者: 全教室
     * - 保護者 (user_type=guardian): 担当する全児童の在籍教室集合
     *   （User::accessibleClassroomIds() が導出する）
     * - それ以外 (スタッフ/通常管理者): classroom_user ピボット +
     *   users.classroom_id の後方互換
     */
    public function myClassrooms(Request $request): JsonResponse
    {
        $user = $request->user();

        // 切替可能な全教室を取得
        $ids = $user->switchableClassroomIds();
        $classrooms = \App\Models\Classroom::query()
            ->whereIn('id', $ids)
            ->select('id', 'classroom_name', 'address', 'phone')
            ->orderBy('classroom_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current_classroom_id' => $user->classroom_id,
                'classrooms' => $classrooms->values(),
            ],
        ]);
    }

    /**
     * アクティブ教室を切り替える
     */
    public function switch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
        ]);

        $user = $request->user();
        $classroomId = $validated['classroom_id'];

        // このユーザーがその教室にアクセスできるか確認
        $accessible = $user->switchableClassroomIds();
        if (!in_array($classroomId, $accessible)) {
            return response()->json([
                'success' => false,
                'message' => 'この教室へのアクセス権限がありません。',
            ], 403);
        }

        $user->classroom_id = $classroomId;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '教室を切り替えました。',
            'data' => [
                'current_classroom_id' => $classroomId,
            ],
        ]);
    }
}

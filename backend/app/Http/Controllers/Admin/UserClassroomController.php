<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserClassroomController extends Controller
{
    /**
     * ユーザーが所属する教室一覧を取得
     */
    public function index(User $user): JsonResponse
    {
        $classrooms = $user->classrooms()
            ->select('classrooms.id', 'classrooms.classroom_name')
            ->orderBy('classrooms.classroom_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'current_classroom_id' => $user->classroom_id,
                'classroom_ids' => $classrooms->pluck('id')->toArray(),
                'classrooms' => $classrooms,
            ],
        ]);
    }

    /**
     * ユーザーの所属教室を同期（置換）
     */
    public function sync(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'classroom_ids' => 'required|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
        ]);

        $ids = $validated['classroom_ids'];
        $user->classrooms()->sync($ids);

        // users.classroom_id が含まれていない場合は最初の教室に変更
        if (!empty($ids) && !in_array($user->classroom_id, $ids)) {
            $user->classroom_id = $ids[0];
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => '所属教室を更新しました。',
        ]);
    }
}

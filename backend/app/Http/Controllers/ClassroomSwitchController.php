<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomSwitchController extends Controller
{
    /**
     * 現在のユーザーが所属する教室一覧を取得
     *
     * マスター管理者は全教室を取得できる。
     */
    public function myClassrooms(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = (bool) ($user->is_master ?? false);

        if ($isMaster) {
            $classrooms = \App\Models\Classroom::query()
                ->select('id', 'classroom_name', 'address', 'phone')
                ->orderBy('classroom_name')
                ->get();
        } else {
            $classrooms = $user->classrooms()
                ->select('classrooms.id', 'classrooms.classroom_name', 'classrooms.address', 'classrooms.phone')
                ->orderBy('classrooms.classroom_name')
                ->get();

            // users.classroom_id にあるが classroom_user にない場合は補完
            if ($user->classroom_id && !$classrooms->pluck('id')->contains($user->classroom_id)) {
                $current = \App\Models\Classroom::select('id', 'classroom_name', 'address', 'phone')
                    ->find($user->classroom_id);
                if ($current) {
                    $classrooms->push($current);
                }
            }
        }

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
        $accessible = $user->accessibleClassroomIds();
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserClassroomController extends Controller
{
    /**
     * ユーザーが所属する教室一覧を取得
     *
     * レスポンスにはフロントエンドが割当可能教室を絞り込むために
     * ユーザーの所属企業 (users.classroom.company_id) も含める。
     */
    public function index(User $user): JsonResponse
    {
        $user->loadMissing('classroom');

        $classrooms = $user->classrooms()
            ->select('classrooms.id', 'classrooms.classroom_name')
            ->orderBy('classrooms.classroom_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'current_classroom_id' => $user->classroom_id,
                'company_id' => $user->classroom?->company_id,
                'classroom_ids' => $classrooms->pluck('id')->toArray(),
                'classrooms' => $classrooms,
            ],
        ]);
    }

    /**
     * ユーザーの所属教室を同期（置換）
     *
     * - 指定される classroom_ids は全てユーザーの所属企業と一致する必要がある
     *   （ユーザーの所属企業は user->classroom->company_id から導出）
     * - ユーザーに現在教室が無い / その教室に所属企業が無い場合は拒否
     *   （先に staff-accounts 側で所属教室と企業を確定してから呼ぶ想定）
     * - 他企業の教室を混ぜて送ったリクエストは 422 で拒否
     */
    public function sync(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'classroom_ids' => 'required|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['classroom_ids'])));

        $user->loadMissing('classroom');
        $userCompanyId = $user->classroom?->company_id;

        if ($userCompanyId === null) {
            throw ValidationException::withMessages([
                'classroom_ids' => ['このユーザーには所属企業が設定されていないため、複数教室を割り当てできません。先に所属教室を設定してください。'],
            ]);
        }

        if (!empty($ids)) {
            $conflicting = Classroom::whereIn('id', $ids)
                ->where(function ($q) use ($userCompanyId) {
                    $q->whereNull('company_id')
                      ->orWhere('company_id', '!=', $userCompanyId);
                })
                ->pluck('classroom_name', 'id');

            if ($conflicting->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'classroom_ids' => [
                        '他の企業に属する教室、または所属企業のない教室が含まれています: '
                            . $conflicting->values()->implode('、'),
                    ],
                ]);
            }
        }

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

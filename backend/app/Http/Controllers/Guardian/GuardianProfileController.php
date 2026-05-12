<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuardianProfileController extends Controller
{
    /**
     * プロフィール情報を取得
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'classroom:id,classroom_name',
            'students:id,student_name,guardian_id,grade_level',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $user->id,
                'username'      => $user->username,
                'full_name'     => $user->full_name,
                'email'         => $user->email,
                'user_type'     => $user->user_type,
                'classroom'     => $user->classroom,
                'students'      => $user->students,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    /**
     * プロフィールを更新
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:100',
            'email'     => 'nullable|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh(),
            'message' => 'プロフィールを更新しました。',
        ]);
    }

    /**
     * パスワードを変更
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '現在のパスワードが正しくありません。',
            ], 422);
        }

        // R-pw-c: 保護者が自分でパスワードを変更したら、スタッフ画面の
        // 「現在のパスワード」表示用 (password_plain) を NULL に落とす。
        // 残しておくとスタッフが古い (= 既に無効な) 平文を見て誤って保護者に
        // 案内してしまう事故が起きる。再発行はスタッフ側の編集画面で行う。
        $user->update([
            'password'       => Hash::make($request->new_password),
            'password_plain' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'パスワードを変更しました。',
        ]);
    }
}

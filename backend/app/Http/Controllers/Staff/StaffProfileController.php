<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffProfileController extends Controller
{
    /**
     * プロフィール情報を取得
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('classroom:id,classroom_name');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $user->id,
                'username'       => $user->username,
                'full_name'      => $user->full_name,
                'email'          => $user->email,
                'user_type'      => $user->user_type,
                'classroom'      => $user->classroom,
                'last_login_at'  => $user->last_login_at,
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

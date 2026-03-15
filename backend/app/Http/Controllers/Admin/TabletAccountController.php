<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TabletAccountController extends Controller
{
    /**
     * タブレットアカウント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('user_type', 'tablet')
            ->with('classroom:id,classroom_name');

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $accounts = $query->orderBy('classroom_id')->orderBy('username')->get();

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    /**
     * タブレットアカウントを作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users,username',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:100',
        ]);

        $account = User::create([
            'classroom_id' => $validated['classroom_id'],
            'username'     => $validated['username'],
            'password'     => Hash::make($validated['password']),
            'full_name'    => $validated['full_name'],
            'user_type'    => 'tablet',
            'is_active'    => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $account,
            'message' => 'タブレットアカウントを作成しました。',
        ], 201);
    }

    /**
     * タブレットアカウントを更新
     */
    public function update(Request $request, User $account): JsonResponse
    {
        if ($account->user_type !== 'tablet') {
            return response()->json(['success' => false, 'message' => 'タブレットアカウントではありません。'], 422);
        }

        $validated = $request->validate([
            'classroom_id' => 'sometimes|exists:classrooms,id',
            'full_name'    => 'sometimes|string|max:100',
            'password'     => 'nullable|string|min:6',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $account->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $account->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * タブレットアカウントの有効/無効を切り替え
     */
    public function toggle(User $account): JsonResponse
    {
        if ($account->user_type !== 'tablet') {
            return response()->json(['success' => false, 'message' => 'タブレットアカウントではありません。'], 422);
        }

        $account->update(['is_active' => ! $account->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $account->is_active,
            'message'   => $account->is_active ? '有効にしました。' : '無効にしました。',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminAccountController extends Controller
{
    /**
     * 管理者アカウント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('user_type', 'admin')->with('classroom');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('full_name')->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * 管理者アカウント詳細を取得
     */
    public function show(User $user): JsonResponse
    {
        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        $user->load('classroom');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * 管理者アカウントを新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['user_type'] = 'admin';

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->load('classroom'),
            'message' => '管理者アカウントを作成しました。',
        ], 201);
    }

    /**
     * 管理者アカウントを更新
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'password'     => 'nullable|string|min:6',
            'full_name'    => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh('classroom'),
            'message' => '管理者アカウントを更新しました。',
        ]);
    }

    /**
     * 管理者アカウントを削除（論理削除）
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '管理者アカウントを無効にしました。',
        ]);
    }

    /**
     * 管理者をスタッフに降格
     */
    public function convertToStaff(Request $request, User $user): JsonResponse
    {
        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 422);
        }

        $user->update(['user_type' => 'staff']);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh(),
            'message' => '管理者をスタッフに変更しました。',
        ]);
    }
}

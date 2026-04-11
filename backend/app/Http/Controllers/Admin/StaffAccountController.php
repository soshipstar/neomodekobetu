<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffAccountController extends Controller
{
    /**
     * マスター管理者のみアクセス可能にする共通チェック
     */
    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->user_type !== 'admin' || !$user->is_master) {
            return response()->json([
                'success' => false,
                'message' => 'マスター管理者権限が必要です。',
            ], 403);
        }
        return null;
    }

    /**
     * スタッフアカウント一覧を取得（マスター管理者専用）
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = User::where('user_type', 'staff')->with('classroom');

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

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

        $users = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 50));

        // フロントエンドはフラットな classroom_name を参照するため、
        // ネストした classroom リレーションから取り出して属性として付与する
        $users->getCollection()->each(function (User $u) {
            $u->classroom_name = $u->classroom?->classroom_name;
        });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * スタッフアカウント詳細を取得（マスター管理者専用）
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'staff') {
            return response()->json(['success' => false, 'message' => 'スタッフアカウントではありません。'], 404);
        }

        $user->load('classroom');
        $user->classroom_name = $user->classroom?->classroom_name;

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * スタッフアカウントを新規作成（マスター管理者専用）
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['user_type'] = 'staff';

        $user = User::create($validated);
        $user->load('classroom');
        $user->classroom_name = $user->classroom?->classroom_name;

        return response()->json([
            'success' => true,
            'data'    => $user,
            'message' => 'スタッフアカウントを作成しました。',
        ], 201);
    }

    /**
     * スタッフアカウントを更新（マスター管理者専用）
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'staff') {
            return response()->json(['success' => false, 'message' => 'スタッフアカウントではありません。'], 404);
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
        $fresh = $user->fresh('classroom');
        $fresh->classroom_name = $fresh->classroom?->classroom_name;

        return response()->json([
            'success' => true,
            'data'    => $fresh,
            'message' => 'スタッフアカウントを更新しました。',
        ]);
    }

    /**
     * スタッフアカウントを削除（マスター管理者専用）
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'staff') {
            return response()->json(['success' => false, 'message' => 'スタッフアカウントではありません。'], 404);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'スタッフアカウントを無効にしました。',
        ]);
    }

    /**
     * スタッフを管理者に昇格（マスター管理者専用）
     * is_master=false（通常管理者）として昇格
     */
    public function convertToAdmin(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'staff') {
            return response()->json(['success' => false, 'message' => 'スタッフアカウントではありません。'], 422);
        }

        $user->update([
            'user_type' => 'admin',
            'is_master' => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh(),
            'message' => 'スタッフアカウントを管理者アカウントに変換しました。',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuardianController extends Controller
{
    /**
     * 保護者一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = User::guardian()
            ->with([
                'classroom:id,classroom_name',
                'students:id,student_name,guardian_id',
            ]);

        // マスター管理者は全教室、通常管理者は自分の教室のみ
        if ($user->is_master) {
            if ($request->filled('classroom_id')) {
                $query->where('classroom_id', $request->classroom_id);
            }
        } else {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $guardians = $query->orderBy('classroom_id')->orderBy('full_name')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $guardians,
        ]);
    }

    /**
     * 保護者を作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users,username',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:100',
            'email'        => 'nullable|email|max:255',
        ]);

        $guardian = User::create([
            'classroom_id' => $validated['classroom_id'],
            'username'     => $validated['username'],
            'password'     => Hash::make($validated['password']),
            'full_name'    => $validated['full_name'],
            'email'        => $validated['email'] ?? null,
            'user_type'    => 'guardian',
            'is_active'    => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $guardian,
            'message' => '保護者を登録しました。',
        ], 201);
    }

    /**
     * 保護者詳細を取得
     */
    public function show(Request $request, User $guardian): JsonResponse
    {
        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        $user = $request->user();
        if (!$user->is_master && $guardian->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $guardian->load([
            'classroom:id,classroom_name',
            'students:id,student_name,guardian_id,grade_level,status',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $guardian,
        ]);
    }

    /**
     * 保護者を更新
     */
    public function update(Request $request, User $guardian): JsonResponse
    {
        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        $user = $request->user();
        if (!$user->is_master && $guardian->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'classroom_id' => 'sometimes|exists:classrooms,id',
            'full_name'    => 'sometimes|string|max:100',
            'email'        => 'nullable|email|max:255',
            'password'     => 'nullable|string|min:6',
            'is_active'    => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $guardian->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $guardian->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * 保護者を削除（論理削除: is_active=false）
     */
    public function destroy(Request $request, User $guardian): JsonResponse
    {
        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        $user = $request->user();
        if (!$user->is_master && $guardian->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $guardian->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '無効化しました。',
        ]);
    }
}

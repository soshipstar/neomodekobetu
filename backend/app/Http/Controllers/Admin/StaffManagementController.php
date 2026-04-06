<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffManagementController extends Controller
{
    /**
     * スタッフ一覧を取得（管理用：シフト・配置などの情報含む）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $query = User::whereIn('user_type', ['staff', 'admin'])
            ->where('is_master', false)
            ->with('classroom');

        // 通常管理者は自教室のみ
        if (!$isMaster && $user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        } elseif ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $staff = $query->orderBy('full_name')->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $staff,
        ]);
    }

    /**
     * スタッフ詳細を取得
     */
    public function show(User $user): JsonResponse
    {
        $user->load('classroom');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * スタッフ情報を更新（配置・役職など）
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->is_master) {
            return response()->json(['success' => false, 'message' => 'マスター管理者は編集できません。'], 403);
        }

        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'full_name'    => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh('classroom'),
            'message' => 'スタッフ情報を更新しました。',
        ]);
    }

    /**
     * スタッフを削除（論理削除）
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->is_master) {
            return response()->json(['success' => false, 'message' => 'マスター管理者は削除できません。'], 403);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'スタッフを無効にしました。',
        ]);
    }
}

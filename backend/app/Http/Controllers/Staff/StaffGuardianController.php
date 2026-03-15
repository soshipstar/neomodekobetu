<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffGuardianController extends Controller
{
    /**
     * 保護者一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = User::guardian()->active()->with('students:id,student_name,guardian_id');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $guardians = $query->orderBy('full_name')->get();

        return response()->json([
            'success' => true,
            'data'    => $guardians,
        ]);
    }

    /**
     * 保護者詳細を取得（マニュアル印刷用にpassword_plain含む）
     */
    public function show(Request $request, User $guardian): JsonResponse
    {
        $user = $request->user();

        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        if ($user->classroom_id && $guardian->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $guardian->load('students:id,student_name,guardian_id');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $guardian->id,
                'full_name'      => $guardian->full_name,
                'username'       => $guardian->username,
                'email'          => $guardian->email,
                'password_plain' => $guardian->password_plain ?? null,
                'students'       => $guardian->students,
            ],
        ]);
    }

    /**
     * 保護者を作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username'  => 'required|string|max:100|unique:users,username',
            'password'  => 'required|string|min:6',
            'full_name' => 'required|string|max:100',
            'email'     => 'nullable|email|max:255',
        ]);

        $guardian = User::create([
            'classroom_id'  => $user->classroom_id,
            'username'      => $validated['username'],
            'password'      => Hash::make($validated['password']),
            'password_plain' => $validated['password'],
            'full_name'     => $validated['full_name'],
            'email'         => $validated['email'] ?? null,
            'user_type'     => 'guardian',
            'is_active'     => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $guardian,
            'message' => '保護者を登録しました。',
        ], 201);
    }

    /**
     * 保護者を更新
     */
    public function update(Request $request, User $guardian): JsonResponse
    {
        $user = $request->user();

        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        if ($user->classroom_id && $guardian->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:100',
            'email'     => 'nullable|email|max:255',
            'password'  => 'nullable|string|min:6',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password_plain'] = $validated['password'];
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
}

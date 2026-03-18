<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClassroomController extends Controller
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
     * 教室一覧を取得（マスター管理者専用）
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = Classroom::withCount(['students', 'users']);

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $classrooms = $query->orderBy('classroom_name')->get();

        return response()->json([
            'success' => true,
            'data'    => $classrooms,
        ]);
    }

    /**
     * 教室を新規作成（マスター管理者専用）
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_name' => 'required|string|max:255',
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'settings'       => 'nullable|array',
            'is_active'      => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('classrooms/logos', 'public');
            $validated['logo_path'] = $path;
        }
        unset($validated['logo']);

        $classroom = Classroom::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $classroom,
            'message' => '教室を作成しました。',
        ], 201);
    }

    /**
     * 教室詳細を取得（マスター管理者専用）
     */
    public function show(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $classroom->load(['students', 'users', 'tags', 'capacity']);
        $classroom->loadCount(['students', 'users']);

        return response()->json([
            'success' => true,
            'data'    => $classroom,
        ]);
    }

    /**
     * 教室を更新（マスター管理者専用）
     */
    public function update(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_name' => 'sometimes|required|string|max:255',
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'settings'       => 'nullable|array',
            'is_active'      => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            // 古いロゴを削除
            if ($classroom->logo_path) {
                Storage::disk('public')->delete($classroom->logo_path);
            }
            $path = $request->file('logo')->store('classrooms/logos', 'public');
            $validated['logo_path'] = $path;
        }
        unset($validated['logo']);

        $classroom->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $classroom->fresh(),
            'message' => '教室を更新しました。',
        ]);
    }

    /**
     * 教室を削除（マスター管理者専用、論理削除 = is_active を false にする）
     */
    public function destroy(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        // 生徒が紐づいている場合は削除不可
        if ($classroom->students()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '生徒が在籍している教室は削除できません。',
            ], 422);
        }

        $classroom->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '教室を無効にしました。',
        ]);
    }
}

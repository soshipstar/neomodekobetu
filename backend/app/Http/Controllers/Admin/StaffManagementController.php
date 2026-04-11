<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * スタッフを新規作成
     *
     * このエンドポイントはリクエストユーザーの現在の classroom_id に対して
     * スタッフを登録する想定。フロントエンドのフォームには classroom 選択が
     * 無いため、リクエストに classroom_id が含まれていなければ、認証ユーザー
     * の classroom_id をデフォルトとして使用する。
     *
     * classroom_id が決まらない場合（master で未切替など）は 422 を返す。
     * 教室には必ず所属企業が設定されていることを要求する。
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users,username',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);

        // classroom_id をリクエスト → 認証ユーザー → 422 の順に決定
        $classroomId = $validated['classroom_id'] ?? $authUser->classroom_id ?? null;
        if (empty($classroomId)) {
            throw ValidationException::withMessages([
                'classroom_id' => ['所属教室が未設定です。教室切替で教室を選択してから登録してください。'],
            ]);
        }

        // 教室は必ず所属企業を持っていること
        $classroom = Classroom::find($classroomId);
        if (!$classroom) {
            throw ValidationException::withMessages([
                'classroom_id' => ['指定した教室は存在しません。'],
            ]);
        }
        if ($classroom->company_id === null) {
            throw ValidationException::withMessages([
                'classroom_id' => ['選択した教室には所属企業が設定されていません。企業に属する教室を選択してください。'],
            ]);
        }

        $user = User::create([
            'classroom_id'     => $classroomId,
            'username'         => $validated['username'],
            'password'         => Hash::make($validated['password']),
            'full_name'        => $validated['full_name'],
            'email'            => $validated['email'] ?? null,
            'user_type'        => 'staff',
            'is_master'        => false,
            'is_company_admin' => false,
            'is_active'        => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $user->load('classroom'),
            'message' => 'スタッフを登録しました。',
        ], 201);
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

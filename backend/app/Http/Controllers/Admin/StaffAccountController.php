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
     * フロントエンド向けに classroom / company の情報をフラットな属性として付与する。
     * users.company_id カラムは削除済みのため、classroom 経由で導出する。
     */
    private function appendFlatClassroomAndCompany(User $user): void
    {
        $user->classroom_name = $user->classroom?->classroom_name;
        $user->company_name   = $user->classroom?->company?->name;
        $user->setAttribute('company_id', $user->classroom?->company_id);
    }

    /**
     * スタッフアカウントの classroom_id が企業に所属していることを保証する。
     *
     * - classroom_id が必須（スタッフは必ず教室に所属する）
     * - 指定された教室は存在すること
     * - その教室は必ずどこかの企業に所属していること（company_id が null でない）
     *
     * 新規作成と更新の両方から呼ぶ。更新時は request に classroom_id が含まれない
     * ときは呼び出し側で既存値をマージして渡すこと。
     */
    private function assertClassroomHasCompany(?int $classroomId): void
    {
        if (empty($classroomId)) {
            throw ValidationException::withMessages([
                'classroom_id' => ['所属教室は必須です。'],
            ]);
        }

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
    }

    /**
     * スタッフアカウント一覧を取得（マスター管理者専用）
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = User::where('user_type', 'staff')->with(['classroom.company']);

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

        // フロントエンドはフラットな classroom_name / company_name / company_id を
        // 参照するため、ネストしたリレーションから取り出して属性として付与する
        $users->getCollection()->each(function (User $u) {
            $this->appendFlatClassroomAndCompany($u);
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

        $user->load(['classroom.company']);
        $this->appendFlatClassroomAndCompany($user);

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
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);
        // 所属企業は classroom 経由で一意に決まるため company_id は受け付けない
        // classroom は必ずどこかの企業に属していること
        $this->assertClassroomHasCompany((int) $validated['classroom_id']);

        $validated['password'] = Hash::make($validated['password']);
        $validated['user_type'] = 'staff';

        $user = User::create($validated);
        $user->load(['classroom.company']);
        $this->appendFlatClassroomAndCompany($user);

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
            'classroom_id' => 'sometimes|required|integer|exists:classrooms,id',
            'username'     => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'password'     => 'nullable|string|min:6',
            'full_name'    => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
        ]);
        // company_id は受け付けない（classroom 経由で導出）
        // classroom_id が含まれるときは、その教室が企業に属していることを保証する
        if (array_key_exists('classroom_id', $validated)) {
            $this->assertClassroomHasCompany((int) $validated['classroom_id']);
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $fresh = $user->fresh(['classroom.company']);
        $this->appendFlatClassroomAndCompany($fresh);

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

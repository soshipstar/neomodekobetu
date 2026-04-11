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
        // API 契約維持のため company_id を動的属性として公開（永続化はされない）
        $user->setAttribute('company_id', $user->classroom?->company_id);
    }

    /**
     * 管理者アカウント一覧を取得（マスター管理者専用）
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = User::where('user_type', 'admin')->with(['classroom.company']);

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

        $users = $query->orderByDesc('is_master')->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 50));

        // フロントエンドはフラットな classroom_name / company_name / company_id を
        // 参照するため、ネストしたリレーションから取り出して属性として付与する。
        // users.company_id カラムは正規化のため削除済み → classroom 経由で導出
        $users->getCollection()->each(function (User $u) {
            $this->appendFlatClassroomAndCompany($u);
        });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * 管理者アカウント詳細を取得（マスター管理者専用）
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        $user->load(['classroom.company']);
        $this->appendFlatClassroomAndCompany($user);

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * 管理者アカウントを新規作成（マスター管理者専用）
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_id'     => 'nullable|exists:classrooms,id',
            'username'         => 'required|string|max:100|unique:users',
            'password'         => 'required|string|min:6',
            'full_name'        => 'required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'is_master'        => 'boolean',
            'is_company_admin' => 'boolean',
            'is_active'        => 'boolean',
        ]);
        // 所属企業は classroom 経由で一意に決まるため company_id は受け付けない

        $validated['password'] = Hash::make($validated['password']);
        $validated['user_type'] = 'admin';

        $user = User::create($validated);
        $user->load(['classroom.company']);
        $this->appendFlatClassroomAndCompany($user);

        return response()->json([
            'success' => true,
            'data'    => $user,
            'message' => '管理者アカウントを作成しました。',
        ], 201);
    }

    /**
     * 管理者アカウントを更新（マスター管理者専用）
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        $validated = $request->validate([
            'classroom_id'     => 'nullable|exists:classrooms,id',
            'username'         => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'password'         => 'nullable|string|min:6',
            'full_name'        => 'sometimes|required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'is_master'        => 'boolean',
            'is_company_admin' => 'boolean',
            'is_active'        => 'boolean',
        ]);
        // company_id は受け付けない（classroom 経由で導出）

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
            'message' => '管理者アカウントを更新しました。',
        ]);
    }

    /**
     * 管理者アカウントを削除（マスター管理者専用）
     * 自分自身は削除できない
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        // 自分自身は削除できない
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => '自分自身のアカウントは削除できません。',
            ], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '管理者アカウントを無効にしました。',
        ]);
    }

    /**
     * 管理者をスタッフに降格（マスター管理者専用）
     * 自分自身は変換できない。is_masterを0にリセット。
     */
    public function convertToStaff(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 422);
        }

        // 自分自身は変換できない
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => '自分自身のアカウントは切り替えできません。',
            ], 422);
        }

        $user->update([
            'user_type' => 'staff',
            'is_master' => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh(),
            'message' => '管理者アカウントをスタッフアカウントに変換しました。',
        ]);
    }
}

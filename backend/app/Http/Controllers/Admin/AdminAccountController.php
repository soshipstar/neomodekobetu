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
     * classroom_id と company_id の整合性を検証する。
     * - classroom が指定されていれば、その classroom.company_id と
     *   送信された company_id は一致していなければならない
     * - 指定が無ければ classroom.company_id を採用する
     *
     * @throws ValidationException
     */
    private function normalizeCompanyFromClassroom(array &$validated): void
    {
        if (empty($validated['classroom_id'])) {
            return;
        }

        $classroom = Classroom::find($validated['classroom_id']);
        if (!$classroom || $classroom->company_id === null) {
            return;
        }

        $submitted = $validated['company_id'] ?? null;
        if ($submitted !== null && (int) $submitted !== (int) $classroom->company_id) {
            throw ValidationException::withMessages([
                'classroom_id' => ['所属教室は選択した所属企業に属している必要があります。'],
            ]);
        }

        // 未指定または一致 → classroom の company_id を採用
        $validated['company_id'] = $classroom->company_id;
    }

    /**
     * 管理者アカウント一覧を取得（マスター管理者専用）
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = User::where('user_type', 'admin')->with(['classroom', 'company']);

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

        // フロントエンドはフラットな classroom_name / company_name を参照するため、
        // ネストしたリレーションから取り出して属性として付与する
        $users->getCollection()->each(function (User $u) {
            $u->classroom_name = $u->classroom?->classroom_name;
            $u->company_name   = $u->company?->name;
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

        $user->load(['classroom', 'company']);
        $user->classroom_name = $user->classroom?->classroom_name;
        $user->company_name   = $user->company?->name;

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
            'company_id'       => 'nullable|exists:companies,id',
            'username'         => 'required|string|max:100|unique:users',
            'password'         => 'required|string|min:6',
            'full_name'        => 'required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'is_master'        => 'boolean',
            'is_company_admin' => 'boolean',
            'is_active'        => 'boolean',
        ]);

        $this->normalizeCompanyFromClassroom($validated);

        $validated['password'] = Hash::make($validated['password']);
        $validated['user_type'] = 'admin';

        $user = User::create($validated);
        $user->load(['classroom', 'company']);
        $user->classroom_name = $user->classroom?->classroom_name;
        $user->company_name   = $user->company?->name;

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
            'company_id'       => 'nullable|exists:companies,id',
            'username'         => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'password'         => 'nullable|string|min:6',
            'full_name'        => 'sometimes|required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'is_master'        => 'boolean',
            'is_company_admin' => 'boolean',
            'is_active'        => 'boolean',
        ]);

        // 更新時も classroom_id が送られてきたら company_id と整合チェック。
        // classroom_id が request に含まれないときは既存値と整合させる。
        if (!array_key_exists('classroom_id', $validated)) {
            $validated['classroom_id'] = $user->classroom_id;
        }
        $this->normalizeCompanyFromClassroom($validated);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $fresh = $user->fresh(['classroom', 'company']);
        $fresh->classroom_name = $fresh->classroom?->classroom_name;
        $fresh->company_name   = $fresh->company?->name;

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

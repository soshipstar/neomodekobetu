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
     * マスター管理者 または 企業管理者 のみアクセス可能にする共通チェック。
     * 企業管理者の場合は自企業の通常管理者のみ操作可能。
     */
    private function requireAdminManager(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者権限が必要です。'], 403);
        }
        if (!$user->is_master && !$user->is_company_admin) {
            return response()->json(['success' => false, 'message' => 'マスター管理者または企業管理者権限が必要です。'], 403);
        }
        return null;
    }

    /**
     * 企業管理者が操作対象ユーザーにアクセスできるかチェック。
     * マスター管理者なら常にtrue。企業管理者は自企業内の通常管理者のみ。
     */
    private function canManageUser(Request $request, User $target): bool
    {
        $me = $request->user();
        if ($me->is_master) return true;

        // 企業管理者: 対象がマスターまたは企業管理者なら操作不可
        if ($target->is_master || $target->is_company_admin) return false;

        // 対象の教室が自企業内かチェック
        $myCompanyId = $me->classroom?->company_id;
        $targetCompanyId = $target->classroom?->company_id;
        return $myCompanyId && $myCompanyId === $targetCompanyId;
    }

    /**
     * フロントエンド向けに classroom / company の情報をフラットな属性として付与する。
     */
    private function appendFlatClassroomAndCompany(User $user): void
    {
        $user->classroom_name = $user->classroom?->classroom_name;
        $user->company_name   = $user->classroom?->company?->name;
        $user->setAttribute('company_id', $user->classroom?->company_id);
    }

    /**
     * 権限に応じて classroom_id の必須条件と所属企業の整合性を検証する。
     */
    private function assertClassroomByRole(array $validated): void
    {
        $isMaster = (bool) ($validated['is_master'] ?? false);
        if ($isMaster) {
            return;
        }

        $classroomId = $validated['classroom_id'] ?? null;
        if (empty($classroomId)) {
            throw ValidationException::withMessages([
                'classroom_id' => ['所属教室は必須です。マスター管理者以外は必ず所属教室を選択してください。'],
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
     * 企業管理者が選択した教室が自企業内かチェック
     */
    private function assertCompanyScope(Request $request, array $validated): void
    {
        $me = $request->user();
        if ($me->is_master) return;

        // 企業管理者はマスター/企業管理者を作成不可
        if (!empty($validated['is_master'])) {
            throw ValidationException::withMessages([
                'is_master' => ['企業管理者はマスター管理者を作成できません。'],
            ]);
        }
        if (!empty($validated['is_company_admin'])) {
            throw ValidationException::withMessages([
                'is_company_admin' => ['企業管理者は他の企業管理者を作成できません。'],
            ]);
        }

        // 教室が自企業内か確認
        $classroomId = $validated['classroom_id'] ?? null;
        if ($classroomId) {
            $classroom = Classroom::find($classroomId);
            $myCompanyId = $me->classroom?->company_id;
            if (!$classroom || $classroom->company_id !== $myCompanyId) {
                throw ValidationException::withMessages([
                    'classroom_id' => ['自企業内の教室のみ選択できます。'],
                ]);
            }
        }
    }

    /**
     * 管理者アカウント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireAdminManager($request)) return $deny;

        $me = $request->user();
        $query = User::where('user_type', 'admin')->with(['classroom.company']);

        // 企業管理者は自企業の通常管理者のみ表示
        if (!$me->is_master) {
            $myCompanyId = $me->classroom?->company_id;
            $companyClassroomIds = $myCompanyId
                ? Classroom::where('company_id', $myCompanyId)->pluck('id')->toArray()
                : [];
            $query->where('is_master', false)
                  ->where('is_company_admin', false)
                  ->whereIn('classroom_id', $companyClassroomIds);
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

        $users = $query->orderByDesc('is_master')->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 50));

        $users->getCollection()->each(function (User $u) {
            $this->appendFlatClassroomAndCompany($u);
        });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * 管理者アカウント詳細を取得
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireAdminManager($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        if (!$this->canManageUser($request, $user)) {
            return response()->json(['success' => false, 'message' => 'このアカウントを管理する権限がありません。'], 403);
        }

        $user->load(['classroom.company']);
        $this->appendFlatClassroomAndCompany($user);

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
        if ($deny = $this->requireAdminManager($request)) return $deny;

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

        $this->assertClassroomByRole($validated);
        $this->assertCompanyScope($request, $validated);

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
     * 管理者アカウントを更新
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireAdminManager($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        if (!$this->canManageUser($request, $user)) {
            return response()->json(['success' => false, 'message' => 'このアカウントを管理する権限がありません。'], 403);
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

        $roleCheckInput = array_merge(
            [
                'classroom_id' => $user->classroom_id,
                'is_master' => (bool) $user->is_master,
                'is_company_admin' => (bool) $user->is_company_admin,
            ],
            $validated
        );
        $this->assertClassroomByRole($roleCheckInput);
        $this->assertCompanyScope($request, $roleCheckInput);

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
     * 管理者アカウントを削除（無効化）
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireAdminManager($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 404);
        }

        if (!$this->canManageUser($request, $user)) {
            return response()->json(['success' => false, 'message' => 'このアカウントを管理する権限がありません。'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => '自分自身のアカウントは削除できません。'], 422);
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
        if ($deny = $this->requireAdminManager($request)) return $deny;

        if ($user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '管理者アカウントではありません。'], 422);
        }

        if (!$this->canManageUser($request, $user)) {
            return response()->json(['success' => false, 'message' => 'このアカウントを管理する権限がありません。'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => '自分自身のアカウントは切り替えできません。'], 422);
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

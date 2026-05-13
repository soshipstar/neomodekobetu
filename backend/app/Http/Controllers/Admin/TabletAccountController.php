<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TabletAccountController extends Controller
{
    /**
     * 自分が管理可能な教室 ID の配列を返す。
     *
     * - マスター管理者: 全教室
     * - 企業管理者 (is_company_admin=true): 自企業の全教室
     * - 通常管理者: 自分の所属教室のみ
     *
     * 企業管理者でも自社の任意教室のタブレットアカウントを CRUD できるようにする
     * (報告: タブレットユーザーが企業管理者から追加できない)
     *
     * @return array<int>
     */
    private function manageableClassroomIds(User $user): array
    {
        if ($user->is_master) {
            return Classroom::query()->pluck('id')->map(fn ($v) => (int) $v)->all();
        }
        if ($user->isCompanyAdmin()) {
            $user->loadMissing('classroom');
            $companyId = $user->classroom?->company_id;
            if ($companyId) {
                return Classroom::where('company_id', $companyId)
                    ->pluck('id')->map(fn ($v) => (int) $v)->all();
            }
        }
        return $user->classroom_id ? [(int) $user->classroom_id] : [];
    }

    /**
     * タブレットアカウント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $manageableIds = $this->manageableClassroomIds($user);

        $query = User::where('user_type', 'tablet')
            ->with('classroom:id,classroom_name');

        if ($user->is_master) {
            if ($request->filled('classroom_id')) {
                $query->where('classroom_id', $request->classroom_id);
            }
        } else {
            // 企業管理者は自社全教室、通常管理者は自教室のみ
            $query->whereIn('classroom_id', $manageableIds ?: [0]);
        }

        $accounts = $query->orderBy('classroom_id')->orderBy('username')->get();

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    /**
     * タブレットアカウントを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users,username',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:100',
        ]);

        // 認可: 指定された classroom_id が自分の管理範囲か検証
        // (R6 同様、企業管理者は同企業内なら任意教室で作成可能)
        $manageableIds = $this->manageableClassroomIds($user);
        if (! in_array((int) $validated['classroom_id'], $manageableIds, true)) {
            return response()->json([
                'success' => false,
                'message' => '指定した教室にタブレットアカウントを作成する権限がありません。',
            ], 403);
        }

        $account = User::create([
            'classroom_id' => $validated['classroom_id'],
            'username'     => $validated['username'],
            'password'     => Hash::make($validated['password']),
            'full_name'    => $validated['full_name'],
            'user_type'    => 'tablet',
            'is_active'    => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $account->load('classroom:id,classroom_name'),
            'message' => 'タブレットアカウントを作成しました。',
        ], 201);
    }

    /**
     * タブレットアカウントを更新
     */
    public function update(Request $request, User $account): JsonResponse
    {
        if ($account->user_type !== 'tablet') {
            return response()->json(['success' => false, 'message' => 'タブレットアカウントではありません。'], 422);
        }

        $user = $request->user();
        $manageableIds = $this->manageableClassroomIds($user);
        if (! in_array((int) $account->classroom_id, $manageableIds, true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'classroom_id' => 'sometimes|exists:classrooms,id',
            'full_name'    => 'sometimes|string|max:100',
            'password'     => 'nullable|string|min:6',
            'is_active'    => 'sometimes|boolean',
        ]);

        // classroom_id を変更する場合も管理範囲チェック
        if (isset($validated['classroom_id'])
            && ! in_array((int) $validated['classroom_id'], $manageableIds, true)) {
            return response()->json([
                'success' => false,
                'message' => '指定した教室への移動権限がありません。',
            ], 403);
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $account->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $account->fresh('classroom:id,classroom_name'),
            'message' => '更新しました。',
        ]);
    }

    /**
     * タブレットアカウントの有効/無効を切り替え
     */
    public function toggle(Request $request, User $account): JsonResponse
    {
        if ($account->user_type !== 'tablet') {
            return response()->json(['success' => false, 'message' => 'タブレットアカウントではありません。'], 422);
        }

        $user = $request->user();
        $manageableIds = $this->manageableClassroomIds($user);
        if (! in_array((int) $account->classroom_id, $manageableIds, true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $account->update(['is_active' => ! $account->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $account->is_active,
            'message'   => $account->is_active ? '有効にしました。' : '無効にしました。',
        ]);
    }
}

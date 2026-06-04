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
     * ① 複数事業所対応: classroom_ids[] を検証して同期用の ID 配列に正規化する。
     *
     * - 主教室 (primaryId = users.classroom_id) は必ず含める
     *   (switchableClassroomIds は pivot ∪ classroom_id だが、表示・整合性のため pivot にも含める)
     * - 各 ID は操作者の管理範囲 (manageableIds) 内であること
     *
     * 権限外の教室 ID が含まれる場合は null を返す (コール側で 403)。
     *
     * @param  array<int>  $ids
     * @param  array<int>  $manageableIds
     * @return array<int>|null
     */
    private function resolveClassroomIds(array $ids, int $primaryId, array $manageableIds): ?array
    {
        $ids = array_values(array_unique(array_map('intval', array_merge($ids, [$primaryId]))));
        foreach ($ids as $cid) {
            if (! in_array($cid, $manageableIds, true)) {
                return null;
            }
        }

        return $ids;
    }

    /**
     * タブレットアカウント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $manageableIds = $this->manageableClassroomIds($user);

        $query = User::where('user_type', 'tablet')
            ->with(['classroom:id,classroom_name', 'classrooms:id,classroom_name']);

        if ($user->is_master) {
            if ($request->filled('classroom_id')) {
                $query->where('classroom_id', $request->classroom_id);
            }
        } else {
            // 企業管理者は自社全教室、通常管理者は自教室のみ
            $query->whereIn('classroom_id', $manageableIds ?: [0]);
        }

        $accounts = $query->orderBy('classroom_id')->orderBy('username')->get();
        // 管理者が一覧から平文パスワードを参照・コピーできるように visible 化
        $accounts->each(function ($a) {
            $a->makeVisible('password_plain');
        });

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
            'classroom_id'    => 'required|exists:classrooms,id',
            'classroom_ids'   => 'sometimes|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
            'username'        => 'required|string|max:100|unique:users,username',
            'password'        => 'required|string|min:6',
            'full_name'       => 'required|string|max:100',
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

        // ① 複数事業所対応: 追加教室も管理範囲内か検証 (作成前にチェックして 403 を返す)
        $syncIds = null;
        if ($request->has('classroom_ids')) {
            $syncIds = $this->resolveClassroomIds(
                $validated['classroom_ids'] ?? [],
                (int) $validated['classroom_id'],
                $manageableIds
            );
            if ($syncIds === null) {
                return response()->json([
                    'success' => false,
                    'message' => '指定した教室の一部に作成権限がありません。',
                ], 403);
            }
        }

        $account = User::create([
            'classroom_id'   => $validated['classroom_id'],
            'username'       => $validated['username'],
            'password'       => Hash::make($validated['password']),
            // B137-bis: 管理者が後から印刷・案内できるよう平文を保存する。
            // 保護者・スタッフアカウント (StaffGuardianController) と同じ運用。
            'password_plain' => $validated['password'],
            'full_name'      => $validated['full_name'],
            'user_type'      => 'tablet',
            'is_active'      => true,
        ]);
        // ① 複数事業所対応: classroom_user ピボットを同期 (主教室含む)
        if ($syncIds !== null) {
            $account->classrooms()->sync($syncIds);
        }

        $account->makeVisible('password_plain');

        return response()->json([
            'success' => true,
            'data'    => $account->load(['classroom:id,classroom_name', 'classrooms:id,classroom_name']),
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
            'classroom_id'    => 'sometimes|exists:classrooms,id',
            'classroom_ids'   => 'sometimes|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
            'full_name'       => 'sometimes|string|max:100',
            'password'        => 'nullable|string|min:6',
            'is_active'       => 'sometimes|boolean',
        ]);

        // classroom_id を変更する場合も管理範囲チェック
        if (isset($validated['classroom_id'])
            && ! in_array((int) $validated['classroom_id'], $manageableIds, true)) {
            return response()->json([
                'success' => false,
                'message' => '指定した教室への移動権限がありません。',
            ], 403);
        }

        // ① 複数事業所対応: classroom_ids[] が指定された場合は管理範囲を検証
        // (主教室は更新後の classroom_id、未指定なら現在の classroom_id)
        $syncIds = null;
        if ($request->has('classroom_ids')) {
            $primaryId = (int) ($validated['classroom_id'] ?? $account->classroom_id);
            $syncIds = $this->resolveClassroomIds(
                $validated['classroom_ids'] ?? [],
                $primaryId,
                $manageableIds
            );
            if ($syncIds === null) {
                return response()->json([
                    'success' => false,
                    'message' => '指定した教室の一部に変更権限がありません。',
                ], 403);
            }
        }

        if (isset($validated['password'])) {
            // B137-bis: 平文を保持して管理者が表示・コピーできるようにする
            $validated['password_plain'] = $validated['password'];
            $validated['password']       = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // classroom_ids はカラムではないため update() の対象から除外
        unset($validated['classroom_ids']);

        $account->update($validated);

        // ① 複数事業所対応: classroom_user ピボットを同期 (主教室含む)
        if ($syncIds !== null) {
            $account->classrooms()->sync($syncIds);
        }

        $fresh = $account->fresh(['classroom:id,classroom_name', 'classrooms:id,classroom_name']);
        if ($fresh) {
            $fresh->makeVisible('password_plain');
        }

        return response()->json([
            'success' => true,
            'data'    => $fresh,
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

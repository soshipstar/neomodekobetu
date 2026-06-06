<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TabletAccountController extends Controller
{
    /**
     * タブレットアカウント一覧を取得。
     * classroom (主教室) と classrooms (横断可能教室、pivot 経由) を併記する。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = User::where('user_type', 'tablet')
            ->with(['classroom:id,classroom_name', 'classrooms:id,classroom_name']);

        if ($user->is_master) {
            if ($request->filled('classroom_id')) {
                $query->where('classroom_id', $request->classroom_id);
            }
        } else {
            // 非マスターは自分のアクセス可能教室 (pivot 含む) に属する tablet を一覧
            $accessibleIds = $user->switchableClassroomIds();
            $query->where(function ($q) use ($accessibleIds) {
                $q->whereIn('classroom_id', $accessibleIds)
                  ->orWhereHas('classrooms', fn ($qq) => $qq->whereIn('classrooms.id', $accessibleIds));
            });
        }

        $accounts = $query->orderBy('classroom_id')->orderBy('username')->get()
            ->map(function (User $a) {
                // 旧フロント互換 (display_name) を維持しつつ、新フィールド classroom_ids も提供
                $arr = $a->toArray();
                $arr['display_name'] = $a->full_name;
                $arr['classroom_ids'] = $a->classrooms->pluck('id')->all();
                $arr['classroom_names'] = $a->classrooms->pluck('classroom_name')->all();
                return $arr;
            });

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    /**
     * タブレットアカウントを作成。
     *
     * 横断可能な教室を pivot で管理する:
     *  - classroom_id (必須): 主教室 (起動時のアクティブ教室)
     *  - classroom_ids (任意配列): 切替可能な全教室の集合。指定しなければ classroom_id 単一
     *  classroom_id は必ず classroom_ids に含めるよう自動補完する。
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $validated = $request->validate([
            'classroom_id'    => 'required|exists:classrooms,id',
            'classroom_ids'   => 'nullable|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
            'username'        => 'required|string|max:100|unique:users,username',
            'password'        => 'required|string|min:6',
            'full_name'       => 'required|string|max:100',
        ]);

        // 非マスターは switchable に含まれる教室のみ指定可
        $primary = (int) $validated['classroom_id'];
        $crossIds = array_values(array_unique(array_map('intval', $validated['classroom_ids'] ?? [$primary])));
        if (!in_array($primary, $crossIds, true)) {
            $crossIds[] = $primary;
        }

        if (!$isMaster) {
            $accessible = $user->switchableClassroomIds();
            foreach ($crossIds as $cid) {
                if (!in_array($cid, $accessible, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => '指定した教室への登録権限がありません。',
                    ], 403);
                }
            }
        }

        // 同一企業境界: 全ての教室が同じ company_id に属することを強制
        $companyIds = Classroom::whereIn('id', $crossIds)->pluck('company_id')->unique()->filter()->values();
        if ($companyIds->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => '横断教室は同一企業内に限定されます。',
            ], 422);
        }

        $account = DB::transaction(function () use ($validated, $primary, $crossIds) {
            $account = User::create([
                'classroom_id' => $primary,
                'username'     => $validated['username'],
                'password'     => Hash::make($validated['password']),
                'full_name'    => $validated['full_name'],
                'user_type'    => 'tablet',
                'is_active'    => true,
            ]);
            $account->classrooms()->sync($crossIds);
            return $account;
        });

        return response()->json([
            'success' => true,
            'data'    => $account->fresh(['classroom', 'classrooms']),
            'message' => 'タブレットアカウントを作成しました。',
        ], 201);
    }

    /**
     * タブレットアカウントを更新。
     *
     * classroom_ids (任意配列) で横断可能教室を上書き同期する。
     * 旧データ互換: 配列を指定しなければ pivot は変更しない。
     */
    public function update(Request $request, User $account): JsonResponse
    {
        if ($account->user_type !== 'tablet') {
            return response()->json(['success' => false, 'message' => 'タブレットアカウントではありません。'], 422);
        }

        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        // 既存所属教室への変更権限チェック
        if (!$isMaster && !in_array((int) $account->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'classroom_id'    => 'sometimes|exists:classrooms,id',
            'classroom_ids'   => 'nullable|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
            'full_name'       => 'sometimes|string|max:100',
            'is_active'       => 'sometimes|boolean',
            'password'        => 'nullable|string|min:6',
        ]);

        DB::transaction(function () use ($account, $user, $validated, $isMaster) {
            // 主教室変更時の権限チェック + 反映
            if (isset($validated['classroom_id'])) {
                $primary = (int) $validated['classroom_id'];
                if (!$isMaster && !in_array($primary, $user->switchableClassroomIds(), true)) {
                    abort(403, '指定した教室への権限がありません。');
                }
                $account->classroom_id = $primary;
            }

            // pivot 同期
            if (isset($validated['classroom_ids'])) {
                $crossIds = array_values(array_unique(array_map('intval', $validated['classroom_ids'])));
                $primary = (int) ($account->classroom_id ?? 0);
                if ($primary && !in_array($primary, $crossIds, true)) {
                    $crossIds[] = $primary;
                }
                if (!$isMaster) {
                    foreach ($crossIds as $cid) {
                        if (!in_array($cid, $user->switchableClassroomIds(), true)) {
                            abort(403, '指定した教室への権限がありません。');
                        }
                    }
                }
                // 同一企業境界
                $companyIds = Classroom::whereIn('id', $crossIds)->pluck('company_id')->unique()->filter()->values();
                if ($companyIds->count() > 1) {
                    abort(422, '横断教室は同一企業内に限定されます。');
                }
                $account->classrooms()->sync($crossIds);
            }

            // その他フィールド
            if (isset($validated['full_name'])) {
                $account->full_name = $validated['full_name'];
            }
            if (isset($validated['is_active'])) {
                $account->is_active = (bool) $validated['is_active'];
            }
            if (!empty($validated['password'])) {
                $account->password = Hash::make($validated['password']);
            }

            $account->save();
        });

        return response()->json([
            'success' => true,
            'data'    => $account->fresh(['classroom', 'classrooms']),
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
        $isMaster = $user->user_type === 'admin' && $user->is_master;
        if (!$isMaster && !in_array((int) $account->classroom_id, $user->switchableClassroomIds(), true)) {
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * ユーザー一覧を取得
     *
     * 認可 (AUTH-02 修正): マスター管理者は全ユーザー、それ以外は自身の
     * switchableClassroomIds() に属するユーザーのみ。マスターアカウントは
     * 一覧から除外する (他者がマスター情報を覗けないようにする)。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $query = User::with('classroom');

        if (! $isMaster) {
            // 非マスターは自身がアクセスできる事業所のユーザーのみ + マスターを除外
            $query->whereIn('classroom_id', $user->switchableClassroomIds())
                  ->where('is_master', false);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

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

        $users = $query->orderBy('full_name')->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * ユーザーを新規作成
     *
     * 認可 (AUTH-02 修正): 非マスターは自身がアクセスできる事業所にのみ
     * ユーザーを作成可能。is_master / is_company_admin は API 経由で立てさせない
     * (権限昇格防止)。
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isMaster = $authUser->user_type === 'admin' && $authUser->is_master;

        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'user_type'    => ['required', Rule::in(['admin', 'staff', 'guardian'])],
            'is_active'    => 'boolean',
        ]);

        // 非マスターは指定教室が自身のアクセス範囲内であることを要求
        if (! $isMaster) {
            $this->authorizeClassroomId($authUser, $validated['classroom_id'] ?? null, '指定した事業所にユーザーを作成する権限がありません。');
        }

        $validated['password'] = Hash::make($validated['password']);
        // 権限昇格防止: マスター/企業管理者フラグは API では立てない
        $validated['is_master'] = false;
        $validated['is_company_admin'] = false;

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->load('classroom'),
            'message' => 'ユーザーを作成しました。',
        ], 201);
    }

    /**
     * ユーザー詳細を取得
     *
     * 認可 (AUTH-02 修正): 非マスターは自身のアクセス範囲のユーザーのみ。
     * マスターアカウントは非マスターから参照不可。
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        $isMaster = $authUser->user_type === 'admin' && $authUser->is_master;
        if (! $isMaster) {
            if ($user->is_master) {
                throw new \Illuminate\Auth\Access\AuthorizationException('このユーザーへのアクセス権限がありません。');
            }
            $this->authorizeClassroomId($authUser, $user->classroom_id, 'このユーザーへのアクセス権限がありません。');
        }

        $user->load('classroom');

        if ($user->isGuardian()) {
            $user->load('students');
        }

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * ユーザーを更新
     *
     * 認可 (AUTH-02 修正): 非マスターは自身のアクセス範囲のユーザーのみ更新可。
     * is_master / is_company_admin の昇格は API では行わせない。
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        $isMaster = $authUser->user_type === 'admin' && $authUser->is_master;
        if (! $isMaster) {
            if ($user->is_master) {
                throw new \Illuminate\Auth\Access\AuthorizationException('このユーザーの更新権限がありません。');
            }
            $this->authorizeClassroomId($authUser, $user->classroom_id, 'このユーザーの更新権限がありません。');
        }

        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'password'     => 'nullable|string|min:6',
            'full_name'    => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'user_type'    => ['sometimes', 'required', Rule::in(['admin', 'staff', 'guardian'])],
            'is_active'    => 'boolean',
        ]);

        // 移動先教室の権限チェック (非マスターのみ)
        if (! $isMaster && isset($validated['classroom_id'])) {
            $this->authorizeClassroomId($authUser, (int) $validated['classroom_id'], '指定した移動先事業所への権限がありません。');
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // 権限昇格防止: マスター/企業管理者フラグはこの API では変更させない
        unset($validated['is_master'], $validated['is_company_admin']);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh('classroom'),
            'message' => 'ユーザーを更新しました。',
        ]);
    }

    /**
     * ユーザーを削除（論理削除）
     *
     * 認可 (AUTH-02 修正): 非マスターは自身のアクセス範囲のユーザーのみ。
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->isMaster()) {
            return response()->json([
                'success' => false,
                'message' => 'マスターユーザーは削除できません。',
            ], 422);
        }

        $authUser = $request->user();
        $isMaster = $authUser->user_type === 'admin' && $authUser->is_master;
        if (! $isMaster) {
            $this->authorizeClassroomId($authUser, $user->classroom_id, 'このユーザーの削除権限がありません。');
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'ユーザーを無効にしました。',
        ]);
    }

    /**
     * CSV/AIパースによる一括登録
     */
    public function bulkRegister(Request $request): JsonResponse
    {
        $request->validate([
            'users'        => 'required|array|min:1',
            'users.*.username'     => 'required|string|max:100',
            'users.*.full_name'    => 'required|string|max:255',
            'users.*.user_type'    => ['required', Rule::in(['staff', 'guardian'])],
            'users.*.classroom_id' => 'nullable|exists:classrooms,id',
            'users.*.email'        => 'nullable|email|max:255',
            'users.*.password'     => 'nullable|string|min:6',
        ]);

        $authUser = $request->user();
        $isMaster = $authUser->user_type === 'admin' && $authUser->is_master;

        $results = [
            'created' => [],
            'errors'  => [],
        ];

        DB::transaction(function () use ($request, &$results, $authUser, $isMaster) {
            foreach ($request->users as $index => $userData) {
                // 認可 (AUTH-13 修正): 非マスターは自身のアクセス範囲の事業所にのみ登録可
                if (! $isMaster && ! $this->canAccessClassroomId($authUser, isset($userData['classroom_id']) ? (int) $userData['classroom_id'] : null)) {
                    $results['errors'][] = [
                        'index'    => $index,
                        'username' => $userData['username'],
                        'message'  => '指定した事業所への登録権限がありません。',
                    ];
                    continue;
                }

                // ユーザー名の重複チェック
                if (User::where('username', $userData['username'])->exists()) {
                    $results['errors'][] = [
                        'index'   => $index,
                        'username' => $userData['username'],
                        'message' => 'ユーザー名が既に存在します。',
                    ];
                    continue;
                }

                $password = $userData['password'] ?? Str::random(8);

                $user = User::create([
                    'classroom_id' => $userData['classroom_id'] ?? null,
                    'username'     => $userData['username'],
                    'password'     => Hash::make($password),
                    'full_name'    => $userData['full_name'],
                    'email'        => $userData['email'] ?? null,
                    'user_type'    => $userData['user_type'],
                    'is_active'    => true,
                    'is_master'        => false,
                    'is_company_admin' => false,
                ]);

                $results['created'][] = [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'full_name' => $user->full_name,
                    'password'  => $password, // 初期パスワードを返す（印刷用）
                ];
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $results,
            'message' => count($results['created']) . '名のユーザーを登録しました。',
        ], 201);
    }
}

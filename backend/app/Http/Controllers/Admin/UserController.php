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
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('classroom');

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
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'user_type'    => ['required', Rule::in(['admin', 'staff', 'guardian'])],
            'is_active'    => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->load('classroom'),
            'message' => 'ユーザーを作成しました。',
        ], 201);
    }

    /**
     * ユーザー詳細を取得
     */
    public function show(User $user): JsonResponse
    {
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
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'username'     => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'password'     => 'nullable|string|min:6',
            'full_name'    => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'user_type'    => ['sometimes', 'required', Rule::in(['admin', 'staff', 'guardian'])],
            'is_active'    => 'boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh('classroom'),
            'message' => 'ユーザーを更新しました。',
        ]);
    }

    /**
     * ユーザーを削除（論理削除）
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->isMaster()) {
            return response()->json([
                'success' => false,
                'message' => 'マスターユーザーは削除できません。',
            ], 422);
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

        $results = [
            'created' => [],
            'errors'  => [],
        ];

        DB::transaction(function () use ($request, &$results) {
            foreach ($request->users as $index => $userData) {
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

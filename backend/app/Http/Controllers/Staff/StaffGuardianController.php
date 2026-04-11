<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffGuardianController extends Controller
{
    /**
     * ユニークなユーザー名を生成（guardian_XXX形式）
     */
    private function generateUniqueUsername(): string
    {
        $last = User::where('username', 'like', 'guardian_%')
            ->orderByRaw("CAST(SUBSTRING(username FROM 'guardian_([0-9]+)') AS INTEGER) DESC NULLS LAST")
            ->value('username');

        $nextNumber = 1;
        if ($last && preg_match('/guardian_(\d+)/', $last, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // 重複確認
        while (User::where('username', $username)->exists()) {
            $nextNumber++;
            $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }

        return $username;
    }

    /**
     * ランダムなパスワードを生成（8文字の英数字）
     */
    private function generateRandomPassword(int $length = 8): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * 保護者一覧を取得（有効・無効両方表示）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = User::guardian()->with('students:id,student_name,guardian_id');

        if ($classroomId) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // レガシーと同じソート: 有効→無効、氏名順
        $guardians = $query->orderByDesc('is_active')->orderBy('full_name')->get();

        // password_plainを含めて返す（$hiddenを一時的に解除）
        $guardians->each(function ($g) {
            $g->makeVisible('password_plain');
        });

        return response()->json([
            'success' => true,
            'data'    => $guardians,
        ]);
    }

    /**
     * 保護者詳細を取得（マニュアル印刷用にpassword_plain含む）
     */
    public function show(Request $request, User $guardian): JsonResponse
    {
        $user = $request->user();

        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        if ($user->classroom_id && !in_array($guardian->classroom_id, $user->accessibleClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $guardian->load('students:id,student_name,guardian_id');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $guardian->id,
                'full_name'      => $guardian->full_name,
                'username'       => $guardian->username,
                'email'          => $guardian->email,
                'password_plain' => $guardian->password_plain ?? null,
                'students'       => $guardian->students,
            ],
        ]);
    }

    /**
     * 保護者を作成（ユーザー名・パスワードは自動生成）
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'email'     => 'nullable|email|max:255',
        ]);

        $username = $this->generateUniqueUsername();
        $password = $this->generateRandomPassword();

        $guardian = User::create([
            'classroom_id'   => $user->classroom_id,
            'username'       => $username,
            'password'       => Hash::make($password),
            'password_plain' => $password,
            'full_name'      => $validated['full_name'],
            'email'          => $validated['email'] ?? null,
            'user_type'      => 'guardian',
            'is_active'      => true,
        ]);

        $guardian->makeVisible('password_plain');

        return response()->json([
            'success' => true,
            'data'    => $guardian,
            'message' => '保護者を登録しました。',
        ], 201);
    }

    /**
     * 保護者を更新
     */
    public function update(Request $request, User $guardian): JsonResponse
    {
        $user = $request->user();

        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        if ($user->classroom_id && !in_array($guardian->classroom_id, $user->accessibleClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:100',
            'username'  => 'sometimes|string|max:100',
            'email'     => 'nullable|email|max:255',
            'password'  => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ]);

        // ユーザー名の重複チェック（自分以外）
        if (isset($validated['username']) && $validated['username'] !== $guardian->username) {
            $exists = User::where('username', $validated['username'])
                ->where('id', '!=', $guardian->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'このユーザー名は既に使用されています。',
                ], 422);
            }
        }

        if (isset($validated['password'])) {
            $validated['password_plain'] = $validated['password'];
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $guardian->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $guardian->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * 保護者を削除（生徒との紐付けも解除）
     */
    public function destroy(Request $request, User $guardian): JsonResponse
    {
        $user = $request->user();

        if ($guardian->user_type !== 'guardian') {
            return response()->json(['success' => false, 'message' => '保護者ではありません。'], 422);
        }

        if ($user->classroom_id && !in_array($guardian->classroom_id, $user->accessibleClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // 生徒との紐付けを解除（アクセス可能な教室全て）
        if ($user->classroom_id) {
            Student::where('guardian_id', $guardian->id)
                ->whereIn('classroom_id', $user->accessibleClassroomIds())
                ->update(['guardian_id' => null]);
        } else {
            Student::where('guardian_id', $guardian->id)
                ->update(['guardian_id' => null]);
        }

        $guardian->delete();

        return response()->json([
            'success' => true,
            'message' => '保護者を削除しました。',
        ]);
    }
}

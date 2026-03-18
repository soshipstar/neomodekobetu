<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\LoginAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ログイン処理
     * 認証情報を検証し、Sanctumトークンとユーザーデータを返す
     * usersテーブルで認証失敗した場合、studentsテーブルにフォールバック
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // AU-003: IP単位のレート制限（5回/15分）
        $throttleKey = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'username' => ["ログイン試行回数が上限を超えました。{$seconds}秒後に再試行してください。"],
            ]);
        }

        $user = User::where('username', $request->username)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            // usersテーブルで認証成功
            return $this->handleUserLogin($request, $user);
        }

        // usersテーブルで認証失敗 -> studentsテーブルにフォールバック
        $student = Student::where('username', $request->username)->first();

        if ($student && $student->password_hash && Hash::check($request->password, $student->password_hash)) {
            // studentsテーブルで認証成功
            return $this->handleStudentLogin($request, $student);
        }

        // 両方で認証失敗
        RateLimiter::hit($throttleKey, 900); // 15分間

        LoginAttempt::create([
            'username'   => $request->username,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success'    => false,
        ]);

        throw ValidationException::withMessages([
            'username' => ['ユーザー名またはパスワードが正しくありません。'],
        ]);
    }

    /**
     * usersテーブルのユーザーログイン処理
     */
    private function handleUserLogin(Request $request, User $user): JsonResponse
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['このアカウントは無効になっています。管理者にお問い合わせください。'],
            ]);
        }

        // レート制限リセット
        RateLimiter::clear('login:' . $request->ip());

        // ログイン試行を記録
        LoginAttempt::create([
            'username'   => $request->username,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success'    => true,
            'user_id'    => $user->id,
        ]);

        // 最終ログイン日時を更新
        $user->update(['last_login_at' => now()]);

        // 既存トークンを削除して新規発行
        $user->tokens()->delete();
        $token = $user->createToken('kiduri-api', [$user->user_type])->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => $user->load('classroom'),
            ],
        ]);
    }

    /**
     * studentsテーブルの生徒ログイン処理
     * 生徒はstudentsテーブルのusername/password_hashで認証する
     * 常にuser_type='student'の専用Userレコードを作成/使用してトークンを発行する
     * これによりCheckUserTypeミドルウェアがstudent APIルートへのアクセスを許可する
     */
    private function handleStudentLogin(Request $request, Student $student): JsonResponse
    {
        // 退所済み・非アクティブチェック
        if ($student->status === 'withdrawn' || ($student->is_active === false)) {
            throw ValidationException::withMessages([
                'username' => ['このアカウントは無効になっています。管理者にお問い合わせください。'],
            ]);
        }

        // レート制限リセット
        RateLimiter::clear('login:' . $request->ip());

        // ログイン試行を記録
        LoginAttempt::create([
            'username'   => $request->username,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success'    => true,
        ]);

        // 最終ログイン日時を更新
        $student->update(['last_login_at' => now()]);

        // 生徒用の専用Userレコードを作成/取得してトークンを発行
        // 保護者が紐付いていてもいなくても、常にuser_type='student'のUserを使う
        // これにより CheckUserType ミドルウェアが student API を許可する
        $studentUser = User::firstOrCreate(
            ['username' => 'student_' . $student->id],
            [
                'password'     => $student->password_hash ?? bcrypt('student_' . $student->id),
                'full_name'    => $student->student_name,
                'user_type'    => 'student',
                'classroom_id' => $student->classroom_id,
                'is_active'    => true,
            ]
        );

        // 既存レコードの場合、名前や教室が変わっている可能性があるので更新
        $studentUser->update([
            'full_name'    => $student->student_name,
            'classroom_id' => $student->classroom_id,
        ]);

        $studentUser->tokens()->delete();
        $token = $studentUser->createToken('kiduri-api', ['student'])->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => $studentUser->load('classroom'),
                'student' => $student->load('classroom'),
                'login_type' => 'student',
            ],
        ]);
    }

    /**
     * ログアウト処理
     * 現在のトークンを無効化する
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'ログアウトしました。',
        ]);
    }

    /**
     * トークンリフレッシュ
     * 現在のトークンを無効化し新しいトークンを発行する
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('kiduri-api', [$user->user_type])->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
            ],
        ]);
    }

    /**
     * 認証済みユーザー情報を返す
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['classroom', 'students']);

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * パスワードリセット
     * メールアドレスに紐づくユーザーのパスワードをリセットする
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'パスワードをリセットしました。',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'パスワードリセットに失敗しました。トークンが無効または期限切れです。',
        ], 422);
    }
}

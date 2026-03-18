<?php

namespace App\Traits;

use App\Models\Student;
use Illuminate\Http\Request;

/**
 * 生徒コントローラー共通: リクエストユーザーから生徒レコードを解決する
 *
 * 生徒ログイン時は user_type='student', username='student_{id}' のUserレコードが
 * 作成される。このトレイトはそのパターンからStudentモデルを取得する。
 */
trait ResolvesStudent
{
    /**
     * リクエストユーザーに紐づく生徒レコードを取得
     */
    protected function getStudent(Request $request): ?Student
    {
        $user = $request->user();

        if ($user instanceof Student) {
            return $user;
        }

        // username が 'student_{id}' パターンの場合、IDで検索
        if (str_starts_with($user->username, 'student_')) {
            $studentId = (int) str_replace('student_', '', $user->username);
            if ($studentId > 0) {
                return Student::find($studentId);
            }
        }

        // フォールバック: usernameの直接一致で検索
        return Student::where('username', $user->username)->first();
    }
}

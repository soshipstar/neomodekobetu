<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * SOSHIP Growth OS（manage.kiduri.xyz）のログイン連携。
 *
 * SOSHIP のログイン画面に入力された「きづりのログインID（username）＋パスワード」を
 * サーバ間で受け取り、kiduri の users で照合してユーザー情報を返す。
 *  - 共有シークレット（services.soship.sso_secret）で SOSHIP からの呼び出しのみ許可。
 *  - トークンは一切発行・削除しない（きづり本体のセッション・連携トークンに影響しない）。
 *  - 対象は事業所スタッフ・管理者のみ（保護者・生徒は不可）。
 *  - 2FA 有効ユーザーはこの経路を許可しない（SOSHIP 側のネイティブログインを使う想定）。
 *
 * 本コントローラは追加のみ。既存の認証フロー（/api/auth/login 等）には影響しない。
 */
class SoshipAuthController extends Controller
{
    public function verifyLogin(Request $request): JsonResponse
    {
        $request->validate([
            'secret' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $expected = (string) config('services.soship.sso_secret');
        if ($expected === '' || ! hash_equals($expected, (string) $request->input('secret'))) {
            return response()->json(['success' => false, 'message' => '認証に失敗しました。'], 401);
        }

        $user = User::where('username', $request->input('username'))->first();

        $authOk = $user
            && $user->is_active
            && in_array($user->user_type, ['staff', 'admin'], true)
            && Hash::check($request->input('password'), $user->password);

        if (! $authOk) {
            return response()->json([
                'success' => false,
                'message' => 'ログインIDまたはパスワードが正しくありません。',
            ], 401);
        }

        // 2FA 有効ユーザーは username+password だけでは通さない（2FA を迂回しないため）。
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'code' => 'two_factor',
                'message' => 'このアカウントは2要素認証が有効なため、この方法では連携できません。',
            ], 403);
        }

        // 企業単位の SOSHIP 連携可否。マスター管理者が企業単位で有効化し、配下事業所へ
        // 適用される classrooms.soship_enabled を参照する。無効企業のスタッフは拒否。
        $user->loadMissing('classroom');
        if (! $user->classroom || ! $user->classroom->soship_enabled) {
            return response()->json([
                'success' => false,
                'code' => 'not_enabled',
                'message' => 'この企業では SOSHIP 連携が有効になっていません。管理者にお問い合わせください。',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'is_company_admin' => (bool) $user->is_company_admin,
                    'company_id' => $user->company_id,
                    'classroom_id' => $user->classroom_id,
                    'accessible_classroom_ids' => $user->switchableClassroomIds(),
                ],
            ],
        ]);
    }
}

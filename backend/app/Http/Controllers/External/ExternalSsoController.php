<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ExternalSsoController extends Controller
{
    /**
     * SSO受入エンドポイント（school.starglobe.xyzからの連携）
     * GET /sso-login?token=<TOKEN>
     */
    public function ssoLogin(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response('トークンが指定されていません', 400);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->post('https://school.starglobe.xyz/api/sso_verify_token.php', [
                    'token' => $token,
                    'app_type' => 'kiduri',
                ]);

            $data = $response->json();

            if (!$data || !($data['success'] ?? false)) {
                return response('SSO認証に失敗しました', 401);
            }

            $appUsername = $data['data']['app_username'] ?? '';
            if (!$appUsername) {
                return response('連携ユーザーが見つかりません', 404);
            }

            // kiduriのユーザーを検索（配布済みIDで）
            $user = User::where('login_id', $appUsername)
                ->orWhere('email', $appUsername)
                ->first();

            if (!$user) {
                return response('きづりにこのユーザーが見つかりません: ' . htmlspecialchars($appUsername) . '<br><a href="https://school.starglobe.xyz/">スタグロ君に戻る</a>', 404);
            }

            // Sanctumトークン発行
            $apiToken = $user->createToken('sso-token')->plainTextToken;

            // フロントエンドにリダイレクト（トークンをlocalStorageに保存）
            $frontendUrl = config('app.frontend_url', 'https://kiduri.xyz');
            return response(
                '<html><head><meta charset="utf-8"><title>ログイン中...</title></head><body>' .
                '<script>' .
                'localStorage.setItem("auth_token","' . addslashes($apiToken) . '");' .
                'localStorage.setItem("user",' . json_encode(json_encode(['id'=>$user->id,'full_name'=>$user->full_name,'email'=>$user->email])) . ');' .
                'window.location.href="' . $frontendUrl . '";' .
                '</script>' .
                '<p>ログイン中... 自動的にリダイレクトされます。</p></body></html>'
            )->header('Content-Type', 'text/html');

        } catch (\Exception $e) {
            return response('SSO処理エラー: ' . $e->getMessage(), 500);
        }
    }
}

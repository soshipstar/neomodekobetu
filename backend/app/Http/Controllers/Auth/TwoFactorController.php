<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * 2 要素認証 (TOTP) の有効化・確認・無効化。
 *
 * 方針:
 *   - マスター管理者のみが利用可 (denyIfNotMaster)。
 *   - enable: シークレット生成 → otpauth URI と手動入力用キーを返す
 *             (この時点ではまだ有効化されない = two_factor_confirmed_at は null)
 *   - confirm: 認証アプリのコードを 1 回検証成功で有効化 + リカバリコード発行
 *   - disable: パスワード再確認のうえ無効化
 *   - ロックアウト防止のため、有効化はコード確認が成功して初めて完了する。
 */
class TwoFactorController extends Controller
{
    public function __construct(private TotpService $totp) {}

    private function denyIfNotMaster(Request $request): ?JsonResponse
    {
        $u = $request->user();
        if (! $u || ! $u->is_master) {
            return response()->json(['success' => false, 'message' => 'この機能はマスター管理者のみ利用できます。'], 403);
        }
        return null;
    }

    /**
     * 現在の 2FA 状態。
     */
    public function status(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;
        $u = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'enabled'        => $u->hasTwoFactorEnabled(),
                // 未確認のシークレットが保留中か (enable 済 confirm 前)
                'pending'        => $u->two_factor_secret !== null && $u->two_factor_confirmed_at === null,
                'recovery_count' => is_array($u->two_factor_recovery_codes) ? count($u->two_factor_recovery_codes) : 0,
            ],
        ]);
    }

    /**
     * 2FA 有効化の開始: シークレットを生成 (未確認状態で保存) し、
     * 認証アプリ登録用の otpauth URI と手動入力キーを返す。
     */
    public function enable(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;
        $u = $request->user();

        if ($u->hasTwoFactorEnabled()) {
            return response()->json(['success' => false, 'message' => '既に 2 要素認証が有効です。'], 422);
        }

        $secret = $this->totp->generateSecret();
        $u->two_factor_secret = $secret;
        $u->two_factor_confirmed_at = null;        // 確認待ち
        $u->two_factor_recovery_codes = null;
        $u->save();

        $label = $u->email ?: $u->username ?: ('uid' . $u->id);
        return response()->json([
            'success' => true,
            'data' => [
                'secret'      => $secret,                                   // 手動入力用
                'otpauth_uri' => $this->totp->otpauthUri($secret, $label),  // QR にもできる
            ],
            'message' => '認証アプリにキーを登録し、表示された 6 桁コードで確認してください。',
        ]);
    }

    /**
     * 認証アプリのコードを検証して 2FA を有効化。成功でリカバリコードを発行。
     */
    public function confirm(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;
        $u = $request->user();

        $validated = $request->validate(['code' => 'required|string']);

        if ($u->two_factor_secret === null) {
            return response()->json(['success' => false, 'message' => '先に 2 要素認証の設定を開始してください。'], 422);
        }

        if (! $this->totp->verify($u->two_factor_secret, $validated['code'])) {
            return response()->json(['success' => false, 'message' => 'コードが正しくありません。認証アプリの時刻同期を確認してください。'], 422);
        }

        $recovery = $this->totp->generateRecoveryCodes();
        $u->two_factor_confirmed_at = now();
        $u->two_factor_recovery_codes = $recovery;
        $u->save();

        return response()->json([
            'success' => true,
            'data' => [
                'recovery_codes' => $recovery,  // 一度だけ表示。安全な場所に保管させる
            ],
            'message' => '2 要素認証を有効にしました。リカバリコードを必ず控えてください (再表示できません)。',
        ]);
    }

    /**
     * 2FA を無効化 (パスワード再確認が必要)。
     */
    public function disable(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;
        $u = $request->user();

        $validated = $request->validate(['password' => 'required|string']);
        if (! Hash::check($validated['password'], $u->password)) {
            return response()->json(['success' => false, 'message' => 'パスワードが正しくありません。'], 422);
        }

        $u->two_factor_secret = null;
        $u->two_factor_confirmed_at = null;
        $u->two_factor_recovery_codes = null;
        $u->save();

        return response()->json(['success' => true, 'message' => '2 要素認証を無効にしました。']);
    }

    /**
     * リカバリコードの再生成 (現在のコードを破棄)。
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;
        $u = $request->user();
        if (! $u->hasTwoFactorEnabled()) {
            return response()->json(['success' => false, 'message' => '2 要素認証が有効ではありません。'], 422);
        }
        $recovery = $this->totp->generateRecoveryCodes();
        $u->two_factor_recovery_codes = $recovery;
        $u->save();
        return response()->json([
            'success' => true,
            'data' => ['recovery_codes' => $recovery],
            'message' => 'リカバリコードを再生成しました。古いコードは無効です。',
        ]);
    }
}

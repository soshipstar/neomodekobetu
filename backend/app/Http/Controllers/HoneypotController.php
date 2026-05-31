<?php

namespace App\Http\Controllers;

use App\Services\ApiAnomalyDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ハニーポット (おとり) エンドポイント。
 *
 * 目的:
 *   通常の画面・サイドバー・サイトマップからは到達しない「いかにも機密データを
 *   一括取得できそう」な fake API を用意する。これを叩いた = ほぼ確実に
 *   API を列挙してアプリを解析している内部ユーザー。
 *
 * 方針 (現場を誤って止めないため):
 *   - 自動 BAN は しない。
 *   - 叩かれたら security_alerts に「🚨 ハニーポット作動」を記録 + マスター管理者へ通知。
 *   - レスポンスは通常の 404 を装い、罠だと気付かせない。
 *
 * 認証必須グループに配置するため、$request->user() は基本的に存在する。
 * (未認証 bot のスキャンは通常 Sanctum で弾かれて、ここまで到達しない)
 */
class HoneypotController extends Controller
{
    public function trap(Request $request, ApiAnomalyDetectionService $anomaly): JsonResponse
    {
        try {
            $anomaly->recordHoneypot(
                $request->user()?->id,
                $request->path(),
                $request->ip(),
            );
        } catch (\Throwable $e) {
            // 記録失敗でも罠の挙動 (404) は崩さない
        }

        // 罠だと気付かれないよう、ありふれた 404 を返す。
        return response()->json(['message' => 'Not Found.'], 404);
    }
}

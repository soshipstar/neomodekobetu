<?php

/*
|--------------------------------------------------------------------------
| セキュリティ / 異常検知の設定
|--------------------------------------------------------------------------
|
| API アクセス監査ログ (api_access_logs) を分析して不正利用を検出する
| ApiAnomalyDetectionService の閾値。すべて .env から上書き可能。
|
| 閾値はいずれも「直近 1 時間あたり 1 ユーザーの件数」。
| 通常運用ではまず到達しない水準にしてあり、超えたらマスター管理者へ通知する。
|
*/

return [
    'anomaly' => [
        // A: 過大な総リクエスト数
        'max_requests_per_hour' => (int) env('ANOMALY_MAX_REQUESTS_PER_HOUR', 1000),

        // B: 403 (権限外アクセス) の連発 = 他事業所/他企業を探っている疑い
        'max_forbidden_per_hour' => (int) env('ANOMALY_MAX_FORBIDDEN_PER_HOUR', 30),

        // C: PDF/CSV/export の連射 = 一括ダウンロードの疑い
        'max_exports_per_hour' => (int) env('ANOMALY_MAX_EXPORTS_PER_HOUR', 30),

        // D: 404 の連発 = API パスのファジング/列挙の疑い
        'max_not_found_per_hour' => (int) env('ANOMALY_MAX_NOT_FOUND_PER_HOUR', 50),

        // 同一 (user_id, rule) を再通知しないクールダウン秒数
        'cooldown_seconds' => (int) env('ANOMALY_COOLDOWN_SECONDS', 6 * 3600),

        // マスター管理者にメールも送るか (false なら in-app 通知のみ)
        'email_master_admins' => (bool) env('ANOMALY_EMAIL_MASTER_ADMINS', true),
    ],
];

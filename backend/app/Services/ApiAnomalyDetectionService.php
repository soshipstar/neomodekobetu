<?php

namespace App\Services;

use App\Models\ApiAccessLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * api_access_logs を分析し、不正利用の疑いがあるユーザーをマスター管理者に通知する。
 *
 * 検出ルール (時間単位、毎時 schedule 経由で実行):
 *   A. 過大リクエスト数 : 1 ユーザーが直近 1 時間に 1000 件以上 API を叩いた
 *   B. 403 連発         : 1 ユーザーが直近 1 時間に 30 件以上 403 を受けた
 *                          (= 自教室外/権限外のリソースを探っている疑い)
 *   C. PDF 連射         : 1 ユーザーが直近 1 時間に 30 件以上 PDF/CSV を取得
 *                          (= 一括ダウンロードの疑い。throttle:export と二重監視)
 *   D. 404 連発         : 1 ユーザーが直近 1 時間に 50 件以上 404 を受けた
 *                          (= API パスの列挙/ファジング疑い)
 *
 * 検出内容は AnomalyAlert としてマスター管理者全員に通知 + Log::warning。
 * 同一 (user_id, rule) は 6 時間以内に再通知しない (cooldown)。
 */
class ApiAnomalyDetectionService
{
    /** 通知のクールダウン秒数 (= 同じユーザー × ルールで何度も通知しない) */
    public const COOLDOWN_SECONDS = 6 * 3600;

    public function run(): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->detectExcessiveRequests());
        $alerts = array_merge($alerts, $this->detectExcessiveForbidden());
        $alerts = array_merge($alerts, $this->detectExcessiveExports());
        $alerts = array_merge($alerts, $this->detectExcessiveNotFound());

        foreach ($alerts as $alert) {
            $this->notifyMasterAdmins($alert);
        }

        return $alerts;
    }

    /** A: 1 ユーザーが直近 1 時間に 1000 件以上 API */
    private function detectExcessiveRequests(): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('cnt', '>=', 1000)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'A_excessive_requests',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ 過大な API リクエスト',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の API を実行しました (通常運用の閾値 1000 を超過)",
        ])->all();
    }

    /** B: 1 ユーザーが直近 1 時間に 30 件以上 403 */
    private function detectExcessiveForbidden(): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->where('status_code', 403)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('cnt', '>=', 30)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'B_excessive_forbidden',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ 権限外アクセスの繰り返し',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の 403 (権限外) を受けました。他事業所/他企業のデータを探っている疑い",
        ])->all();
    }

    /** C: 1 ユーザーが直近 1 時間に 30 件以上 PDF/CSV */
    private function detectExcessiveExports(): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->whereNotNull('user_id')
            ->where(function ($q) {
                $q->where('path', 'like', '%pdf%')
                  ->orWhere('path', 'like', '%csv%')
                  ->orWhere('path', 'like', '%export%');
            })
            ->groupBy('user_id')
            ->having('cnt', '>=', 30)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'C_excessive_exports',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ PDF/CSV の連続ダウンロード',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の PDF/CSV を取得しました (一括吸い上げの疑い)",
        ])->all();
    }

    /** D: 1 ユーザーが直近 1 時間に 50 件以上 404 */
    private function detectExcessiveNotFound(): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->where('status_code', 404)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('cnt', '>=', 50)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'D_excessive_not_found',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ 存在しないパスへのアクセス連発',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の 404 を受けました。API パスのファジング疑い",
        ])->all();
    }

    /**
     * マスター管理者全員に通知 + Log::warning。
     * (user_id, rule) でクールダウン制御し、6 時間以内の重複通知を抑止。
     */
    private function notifyMasterAdmins(array $alert): void
    {
        // クールダウンチェック (cache-driver = file/redis 想定。ここでは DB の最終通知時刻
        // を持つほど凝らず、Laravel Cache を使う)
        $cooldownKey = "api_anomaly:{$alert['rule']}:{$alert['user_id']}";
        if (cache()->has($cooldownKey)) {
            return;
        }
        cache()->put($cooldownKey, now()->toDateTimeString(), self::COOLDOWN_SECONDS);

        // ログには必ず残す
        Log::warning('API anomaly detected', $alert);

        // マスター管理者にプッシュ/メール通知
        $masters = User::where('is_master', true)->where('is_active', true)->get();
        if ($masters->isEmpty()) return;

        $notif = app(NotificationService::class);
        $body = $alert['body'] . "\n\n対象ユーザーの監査ログ: /admin/error-logs (audit_logs)";
        foreach ($masters as $master) {
            try {
                $notif->notify($master, 'security_alert', $alert['title'], $body, [
                    'rule'    => $alert['rule'],
                    'user_id' => $alert['user_id'],
                    'count'   => $alert['count'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to notify master about anomaly: ' . $e->getMessage());
            }
        }
    }
}

<?php

namespace App\Services;

use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * api_access_logs を分析し、不正利用の疑いがあるユーザーを検出する。
 *
 * 検出ルール (時間単位、毎時 schedule 経由で実行):
 *   A. 過大リクエスト数 : 1 ユーザーが直近 1 時間に N 件以上 API
 *   B. 403 連発         : 直近 1 時間に N 件以上 403 (権限外探索の疑い)
 *   C. PDF 連射         : 直近 1 時間に N 件以上 PDF/CSV/export (一括 DL の疑い)
 *   D. 404 連発         : 直近 1 時間に N 件以上 404 (パスファジングの疑い)
 *
 * 各閾値は config/security.php (env 上書き可)。
 *
 * 検出ごとに:
 *   - security_alerts テーブルに 1 レコード保存 (画面で履歴閲覧 / 対処管理)
 *   - マスター管理者に in-app 通知 (+ config で有効ならメールも)
 *   - Log::warning
 * 同一 (user_id, rule, 時間帯) は unique 制約 + cooldown で重複通知しない。
 */
class ApiAnomalyDetectionService
{
    public function run(): array
    {
        $cfg = config('security.anomaly');

        $detected = [];
        $detected = array_merge($detected, $this->detectExcessiveRequests((int) $cfg['max_requests_per_hour']));
        $detected = array_merge($detected, $this->detectExcessiveForbidden((int) $cfg['max_forbidden_per_hour']));
        $detected = array_merge($detected, $this->detectExcessiveExports((int) $cfg['max_exports_per_hour']));
        $detected = array_merge($detected, $this->detectExcessiveNotFound((int) $cfg['max_not_found_per_hour']));

        $saved = [];
        foreach ($detected as $alert) {
            $record = $this->persist($alert);
            if ($record) {
                $this->notifyMasterAdmins($alert, (bool) $cfg['email_master_admins'], (int) $cfg['cooldown_seconds']);
                $saved[] = $alert;
            }
        }

        return $saved;
    }

    /** A: 1 ユーザーが直近 1 時間に max 件以上 API */
    private function detectExcessiveRequests(int $max): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('cnt', '>=', $max)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'A_excessive_requests',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ 過大な API リクエスト',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の API を実行しました (閾値 {$max} を超過)",
        ])->all();
    }

    /** B: 1 ユーザーが直近 1 時間に max 件以上 403 */
    private function detectExcessiveForbidden(int $max): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->where('status_code', 403)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('cnt', '>=', $max)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'B_excessive_forbidden',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ 権限外アクセスの繰り返し',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の 403 (権限外) を受けました。他事業所/他企業のデータを探っている疑い (閾値 {$max})",
        ])->all();
    }

    /** C: 1 ユーザーが直近 1 時間に max 件以上 PDF/CSV/export */
    private function detectExcessiveExports(int $max): array
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
            ->having('cnt', '>=', $max)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'C_excessive_exports',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ PDF/CSV の連続ダウンロード',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の PDF/CSV を取得しました (一括吸い上げの疑い、閾値 {$max})",
        ])->all();
    }

    /** D: 1 ユーザーが直近 1 時間に max 件以上 404 */
    private function detectExcessiveNotFound(int $max): array
    {
        $rows = DB::table('api_access_logs')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subHour())
            ->where('status_code', 404)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('cnt', '>=', $max)
            ->get();

        return $rows->map(fn ($r) => [
            'rule'    => 'D_excessive_not_found',
            'user_id' => (int) $r->user_id,
            'count'   => (int) $r->cnt,
            'title'   => '⚠ 存在しないパスへのアクセス連発',
            'body'    => "uid={$r->user_id} が直近 1 時間で {$r->cnt} 件の 404 を受けました。API パスのファジング疑い (閾値 {$max})",
        ])->all();
    }

    /**
     * security_alerts に保存。
     * (rule, user_id, detected_hour) unique なので、同じ時間帯の同ルールは
     * 1 回しか保存されない。重複時は null を返し、通知もスキップさせる。
     */
    private function persist(array $alert): ?SecurityAlert
    {
        $hour = now()->startOfHour();
        $user = User::find($alert['user_id']);

        $existing = SecurityAlert::where('rule', $alert['rule'])
            ->where('user_id', $alert['user_id'])
            ->where('detected_hour', $hour)
            ->first();
        if ($existing) {
            return null; // 同時間帯・同ルールは既に保存済 → 通知もしない
        }

        try {
            return SecurityAlert::create([
                'rule'          => $alert['rule'],
                'user_id'       => $alert['user_id'],
                'user_name'     => $user?->full_name,
                'user_type'     => $user?->user_type,
                'count'         => $alert['count'],
                'title'         => $alert['title'],
                'body'          => $alert['body'],
                'detected_hour' => $hour,
                'is_resolved'   => false,
            ]);
        } catch (\Throwable $e) {
            // unique 競合 (並行実行) は無視
            Log::warning('SecurityAlert persist failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * マスター管理者全員に通知 + Log::warning。
     * cooldown 内は in-app/メール通知をスキップ (DB 保存は persist 側で済)。
     */
    private function notifyMasterAdmins(array $alert, bool $withEmail, int $cooldownSeconds): void
    {
        Log::warning('API anomaly detected', $alert);

        $cooldownKey = "api_anomaly:{$alert['rule']}:{$alert['user_id']}";
        if (cache()->has($cooldownKey)) {
            return;
        }
        cache()->put($cooldownKey, now()->toDateTimeString(), $cooldownSeconds);

        $masters = User::where('is_master', true)->where('is_active', true)->get();
        if ($masters->isEmpty()) return;

        $notif = app(NotificationService::class);
        $body = $alert['body'] . "\n\n詳細は管理画面「セキュリティアラート」/「アクセスログ」で確認してください。";
        $data = ['rule' => $alert['rule'], 'user_id' => $alert['user_id'], 'count' => $alert['count']];
        foreach ($masters as $master) {
            try {
                if ($withEmail) {
                    // notifyWithEmail(user, type, title, body, emailTemplate, emailData, notificationData)
                    // 汎用テンプレ emails.notification は $title / $body を受け取る。
                    $notif->notifyWithEmail(
                        $master,
                        'security_alert',
                        $alert['title'],
                        $body,
                        'notification',
                        ['title' => $alert['title'], 'body' => $body],
                        $data,
                    );
                } else {
                    $notif->notify($master, 'security_alert', $alert['title'], $body, $data);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to notify master about anomaly: ' . $e->getMessage());
            }
        }
    }
}

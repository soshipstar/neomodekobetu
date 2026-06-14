<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\AutoGenerateAssessmentPeriodJob;
use App\Jobs\SendDeadlineNotificationsJob;

/*
|--------------------------------------------------------------------------
| Console Routes (Scheduled Tasks)
|--------------------------------------------------------------------------
|
| 定期実行タスクの設定。
| `php artisan schedule:work` または cron で実行される。
|
*/

// アセスメント期間の自動生成 - 毎月1日の午前0時に実行
Schedule::job(new AutoGenerateAssessmentPeriodJob())
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->onOneServer();

// 期限通知 - 毎朝9時に実行
Schedule::job(new SendDeadlineNotificationsJob())
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();

// 古いチャットメッセージの自動削除 - 毎日午前3時に実行
Schedule::command('chat:delete-old')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// 古い事業所写真の自動削除 - 毎日午前3時30分に実行 (90日より古い、かつ連絡帳未添付の写真のみ)
//   実利用ペース (てらこやプラス ~30日, narZE ~80日で 100MB 上限) を考慮し
//   90 日でクリーンアップ。連絡帳に添付済みの写真は保護者の閲覧履歴として残す。
Schedule::command('photos:cleanup-old --days=90')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// 代理店の月次手数料を毎月1日 02:00 に前月分を集計（draft 状態で作成）
// マスター管理者が手動で finalize → mark-paid を行うフロー。
Schedule::command('agent-payouts:calculate')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->onOneServer();

// AI学習基盤 Layer2: 修正傾向ロールアップ(ai_edit_metrics)を毎日 04:30 に当月分を再計算。
// 冪等(delete→insert)・同意済みのみ・k匿名。直近の蓄積をレポートへ反映する。
Schedule::command('ai:rebuild-edit-metrics')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();

// 支援知蒸留(support_knowledge)を毎日 05:00 に法人内で再計算。同意あり法人のみ・k匿名。
Schedule::command('ai:rebuild-knowledge')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->onOneServer();

// 解決済みエラーログの自動削除 - 3日経過したものを毎日午前4時に削除
Schedule::call(function () {
    // error_logs には updated_at 列が無い (created_at のみ)。
    // 解決済みログを作成から3日経過で削除する。
    $deleted = \App\Models\ErrorLog::where('is_resolved', true)
        ->where('created_at', '<', now()->subDays(3))
        ->delete();
    if ($deleted > 0) {
        \Illuminate\Support\Facades\Log::info("Deleted {$deleted} resolved error logs older than 3 days");
    }
})->dailyAt('04:00')->name('cleanup-resolved-error-logs')->onOneServer();

// API アクセスログの自動削除 - 90 日経過したものを毎日午前 4:15 に削除
// (流出/不正解析の後追いに 3 か月あれば十分。DB 肥大化を防ぐ。)
Schedule::call(function () {
    $deleted = \App\Models\ApiAccessLog::where('created_at', '<', now()->subDays(90))->delete();
    if ($deleted > 0) {
        \Illuminate\Support\Facades\Log::info("Deleted {$deleted} api_access_logs older than 90 days");
    }
})->dailyAt('04:15')->name('cleanup-api-access-logs')->onOneServer();

// API 異常検知 - 毎時 0 分に実行。
//   直近 1 時間の api_access_logs を分析し、過大リクエスト/403連発/PDF連射/404連発
//   を検出してマスター管理者に通知する (security_alert)。
//   同じ (user_id, rule) は 6 時間以内は再通知しないクールダウン付き。
Schedule::call(function () {
    $alerts = app(\App\Services\ApiAnomalyDetectionService::class)->run();
    if (!empty($alerts)) {
        \Illuminate\Support\Facades\Log::info('API anomaly detection: ' . count($alerts) . ' alert(s)');
    }
})->hourly()->name('api-anomaly-detection')->onOneServer();

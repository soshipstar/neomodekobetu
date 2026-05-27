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

// 解決済みエラーログの自動削除 - 3日経過したものを毎日午前4時に削除
Schedule::call(function () {
    $deleted = \App\Models\ErrorLog::where('is_resolved', true)
        ->where('updated_at', '<', now()->subDays(3))
        ->delete();
    if ($deleted > 0) {
        \Illuminate\Support\Facades\Log::info("Deleted {$deleted} resolved error logs older than 3 days");
    }
})->dailyAt('04:00')->name('cleanup-resolved-error-logs')->onOneServer();

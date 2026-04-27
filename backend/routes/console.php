<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\AutoGenerateKakehashiPeriodJob;
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

// かけはし期間の自動生成 - 毎月1日の午前0時に実行
Schedule::job(new AutoGenerateKakehashiPeriodJob())
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

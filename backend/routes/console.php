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

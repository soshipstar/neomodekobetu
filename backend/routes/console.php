<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\AutoGenerateKakehashiPeriodJob;

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

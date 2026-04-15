<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class TZ001_AppTimezoneTest extends TestCase
{
    /**
     * TZ-001: アプリの timezone が Asia/Tokyo であることを保証する。
     * これが UTC のままだと naked datetime 入力（<input type="datetime-local">）が
     * UTC 扱いで保存され、面談時刻などが 9 時間ズレる。
     */
    public function test_app_timezone_is_asia_tokyo(): void
    {
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', date_default_timezone_get());
    }

    public function test_naked_datetime_parses_as_jst(): void
    {
        $parsed = Carbon::parse('2026-04-20T14:00');
        $this->assertSame('Asia/Tokyo', $parsed->timezone->getName());
        $this->assertSame('2026-04-20 05:00:00', $parsed->clone()->utc()->format('Y-m-d H:i:s'));
    }
}

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Laravel 12 の framework 同梱 config/app.php は 'UTC' 固定で .env の
    | APP_TIMEZONE を読まないため、ここで上書きする。
    | この設定が無いと Carbon / `now()` / datetime cast が全て UTC 扱いとなり、
    | 面談時刻など naked local datetime が 9 時間ズレて保存・表示される。
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Tokyo'),
];

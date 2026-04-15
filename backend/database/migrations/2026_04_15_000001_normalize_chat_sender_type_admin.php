<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // admin ユーザーが送信したチャットメッセージの sender_type を 'staff' に正規化する。
        // これをしないと未読数クエリ `sender_type != 'staff'` で自分の送信分が未読扱いされる。
        DB::table('chat_messages')->where('sender_type', 'admin')->update(['sender_type' => 'staff']);
    }

    public function down(): void
    {
        // 元の user_type を復元する方法はない（ロスレスでないため no-op）
    }
};

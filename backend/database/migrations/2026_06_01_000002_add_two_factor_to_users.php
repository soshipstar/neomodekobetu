<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users に 2 要素認証 (TOTP) 用カラムを追加。
 *
 * 方針:
 *   - マスター管理者のみが任意で有効化できる (アプリ側で制限)。
 *   - secret と recovery_codes は Laravel の encrypted cast で暗号化保存。
 *     DB ダンプが漏れても APP_KEY なしには利用できない。
 *   - 既存ユーザーは全カラム null = 2FA 無効。ログイン挙動は一切変わらない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // TOTP の共有シークレット (base32)。確認前から保存し、confirm で有効化。
            $table->text('two_factor_secret')->nullable()->after('password');
            // 初回コード確認が成功した日時。null = まだ有効化されていない。
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            // リカバリコード (JSON 配列、使用済みは取り除く)。
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes']);
        });
    }
};

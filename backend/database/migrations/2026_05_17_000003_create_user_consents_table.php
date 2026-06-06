<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザーの同意取得記録テーブル。
 *
 * 背景 (AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 / 個人情報保護法対応):
 *   - プライバシーポリシー / 利用規約 / AI 利用方針 への明示的同意の記録
 *   - 個情法 28 条 (外国にある第三者への提供) として OpenAI 米国送信への個別同意記録
 *   - 同意撤回 (revoke) の記録と、撤回後の AI 機能制御の基礎
 *   - 監査要請に対するエビデンス (誰が・いつ・どの規約バージョンに・どの IP/UA で同意したか)
 *
 * consent_type 例:
 *   - 'privacy_policy'  プライバシーポリシー
 *   - 'terms'           利用規約
 *   - 'ai_usage'        AI 利用方針 (OpenAI 米国送信 / 仮名化処理 / 学習利用の有無)
 *   - 'child_ai_consent' (guardian 限定) 担当児童の記録を AI で処理することへの同意
 *
 * version: 規約改定時にインクリメント (例: 'v1.0', 'v1.1')。
 * granted_at / revoked_at: NULL でなければ撤回状態。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('consent_type', 50)
                ->comment('privacy_policy / terms / ai_usage / child_ai_consent');
            $table->string('version', 20)->comment('規約バージョン (例: v1.0)');
            // 任意: child_ai_consent の場合に対象児童を限定するため
            $table->foreignId('student_id')->nullable()
                ->constrained('students')->nullOnDelete();
            $table->boolean('granted')->default(true)
                ->comment('true=同意有効、false=撤回');
            $table->timestampTz('granted_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'consent_type']);
            $table->index(['user_id', 'consent_type', 'version']);
            $table->index(['student_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};

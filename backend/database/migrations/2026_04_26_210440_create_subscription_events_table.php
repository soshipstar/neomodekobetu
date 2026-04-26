<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe Webhook のイベントログ（リプレイ・冪等性確保用）。
 *
 * - stripe_event_id を unique にして同一イベントの二重処理を防ぐ
 * - payload は監査・障害時のリプレイ用に保持
 * - processed_at で処理完了をマーク（NULL は未処理＝リトライ対象）
 * - error は処理失敗時のメモ（手動リプレイの判断材料）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type', 100)->index();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['type', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};

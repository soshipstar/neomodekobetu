<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe Invoice のローカルキャッシュ。Webhook で同期する。
 *
 * Cashier はデフォルトで Stripe API から都度取得するが、
 * - マスター管理者の「表示制御」（最近12ヶ月のみ表示など）でフィルタしたい
 * - 一覧表示を高速化したい
 * - 請求情報の変遷を監査ログとして残したい
 * という要件のためローカルにキャッシュする。
 *
 * 金額は Stripe API と同じく最小通貨単位（JPY なら円そのまま、整数）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_subscription_id')->nullable()->index();

            $table->string('number')->nullable();
            $table->string('status', 32);

            $table->unsignedBigInteger('amount_due')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('amount_remaining')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->nullable();
            $table->unsignedBigInteger('total')->default(0);
            $table->string('currency', 8)->default('jpy');

            $table->string('hosted_invoice_url', 1024)->nullable();
            $table->string('invoice_pdf', 1024)->nullable();

            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
